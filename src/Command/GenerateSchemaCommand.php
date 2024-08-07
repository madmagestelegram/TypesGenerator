<?php

declare(strict_types=1);

namespace MadmagesTelegram\TypesGenerator\Command;

use JsonException;
use MadmagesTelegram\TypesGenerator\Dictionary\Classes;
use MadmagesTelegram\TypesGenerator\Dictionary\File;
use MadmagesTelegram\TypesGenerator\Dictionary\Namespaces;
use MadmagesTelegram\TypesGenerator\Dictionary\Token;
use MadmagesTelegram\TypesGenerator\Dictionary\Types;
use MadmagesTelegram\TypesGenerator\Kernel;
use ParseError;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use function count;
use function in_array;

class GenerateSchemaCommand extends Command
{

    private const BOT_DOCUMENTATION_URL = 'https://core.telegram.org/bots/api';

    private const TABLE_TYPE_ONE = 1;
    private const TABLE_TYPE_TWO = 2;

    private array $schema;
    private Kernel $kernel;

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct();

        $this->kernel = $kernel;
    }

    protected function configure(): void
    {
        $this
            ->setName('generate:schema')
            ->addOption('schema-path', 's', InputOption::VALUE_OPTIONAL, 'Path to html file', 'schema.html')
            ->addOption('output');
    }

    /**
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $buildDirSource = $this->getBuildDirectory();
        $htmlPath = $buildDirSource . File::DEFAULT_HTML_NAME;
        $schemaPath = $buildDirSource . File::DEFAULT_SCHEMA_NAME;

        if (
            !is_dir($buildDirSource)
            && !mkdir($buildDirSource, 0777, true)
            // Check, is directory created
            && !is_dir($buildDirSource)
        ) {
            throw new RuntimeException("Failed to create build directory: {$buildDirSource}");
        }

        if (!file_exists($htmlPath)) {
            $output->writeln("Html file not exists. Downloading from: " . self::BOT_DOCUMENTATION_URL);
            if (
                !($contents = file_get_contents(self::BOT_DOCUMENTATION_URL))
                || file_put_contents($htmlPath, $contents) === false
            ) {
                throw new RuntimeException("Failed to download: " . self::BOT_DOCUMENTATION_URL);
            }
        } else {
            $output->writeln("Using existing html: {$htmlPath}");
        }

        if (($html = file_get_contents($htmlPath)) === false) {
            throw new RuntimeException("Failed to read html: {$htmlPath}");
        }

        $output->writeln('Building schema...');
        $this->buildSchema($html);

        $output->writeln('Writing...');
        if (
            file_put_contents(
                $schemaPath,
                json_encode($this->schema, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) === false
        ) {
            throw new RuntimeException("Failed to write schema: {$schemaPath}");
        }

        $output->writeln("Done: {$schemaPath}");

        return 0;
    }

    private function getBuildDirectory(): string
    {
        return $this->kernel->getProjectDir() . '/var/build/';
    }

    private function buildSchema($html): void
    {
        $this->schema = [
            'types' => [],
            'methods' => [],
        ];

        $tgItems = [];
        $previousNodeName = null;
        $methodOrTypeName = null;
        $isStarted = false;
        (new Crawler($html))
            ->filter('#dev_page_content > *')
            ->each(
                function (Crawler $pageNode) use (&$tgItems, &$methodOrTypeName, &$previousNodeName, &$isStarted) {
                    if (!$isStarted) {
                        if (
                            $pageNode->nodeName() === 'h3'
                            && stripos($pageNode->text(), 'Getting updates') === 0
                        ) {
                            $isStarted = true;
                        }

                        return;
                    }

                    if ($pageNode->nodeName() === 'h4') {
                        $methodOrTypeName = $pageNode->text();
                        $tgItems[$methodOrTypeName]['isType'] = ctype_upper($methodOrTypeName[0]);
                        $tgItems[$methodOrTypeName]['link'] = $pageNode->filter('a')->attr('href');
                    }

                    if (
                        !empty($methodOrTypeName)
                        && in_array($previousNodeName, ['h4', 'p', 'blockquote'], true)
                    ) {
                        if ($pageNode->nodeName() === 'p') {
                            $tgItems[$methodOrTypeName]['descriptions'][] = $pageNode->html();
                        }

                        if ($pageNode->nodeName() === 'table' && $pageNode->attr('class') === 'table') {
                            if (isset($tgItems[$methodOrTypeName]['table'])) {
                                throw new ParseError('Expecting just one table after type name');
                            }

                            $tgItems[$methodOrTypeName]['table'] = $pageNode;
                        }
                    }

                    $previousNodeName = $pageNode->nodeName();
                }
            );

        foreach ($tgItems as $itemName => $tgItem) {
            if (
                !$tgItem['isType']
                || isset(Types::ALIAS_TYPES[$itemName])
                || in_array($itemName, Types::SKIP_TYPES, true)
            ) {
                continue;
            }

            $fields = [];
            if (isset($tgItem['table'])) {
                /** @var Crawler $tableNode */
                $tableNode = $tgItem['table'];

                $tableType = $this->getTableType($tableNode);
                $tableNode->filter('tbody tr')->each(
                    function (Crawler $rowNode) use (&$fields, $tableType) {
                        if ($tableType === self::TABLE_TYPE_ONE) {
                            $optionalContainer = $rowNode->filter('td:nth-child(3)')->text();
                        } else {
                            $optionalContainer = $rowNode->filter('td:nth-child(4)')->text();
                        }

                        $fields[] = [
                            'name' => $rowNode->filter('td:nth-child(1)')->text(),
                            'rawType' => $rowNode->filter('td:nth-child(2)')->text(),
                            'description' => $rowNode->filter('td:nth-child(3)')->text(),
                            'required' => stripos($optionalContainer, 'optional') === false,
                        ];
                    }
                );
            }

            if (empty($fields) && !in_array($itemName, Types::ALLOWED_EMPTY_TYPES, true)) {
                throw new RuntimeException('Empty fields on type: ' . $itemName);
            }

            $this->schema['types'][$itemName] = [
                'name' => $itemName,
                'link' => $this->makeLinkForSchema($tgItem['link']),
                'descriptions' => $tgItem['descriptions'],
                'fields' => $fields,
            ];
        }

        foreach ($this->schema['types'] as $typeIndex => $type) {
            foreach ($this->schema['types'][$typeIndex]['fields'] as $fieldIndex => $field) {
                $this->schema['types'][$typeIndex]['fields'][$fieldIndex]['type'] = $this->parseType($field['rawType']);
                $this->schema['types'][$typeIndex]['fields'][$fieldIndex]['restrictions'] = $this->getRestrictions(
                    $field['description']
                );

                unset($this->schema['types'][$typeIndex]['fields'][$fieldIndex]['rawType']);
            }
            $this->schema['types'][$typeIndex]['parent'] = $this->getParent($type['name']);
        }

        foreach ($tgItems as $itemName => $tgItem) {
            if ($tgItem['isType']) {
                continue;
            }

            $parameters = [];
            if (isset($tgItem['table'])) {
                /** @var Crawler $tableNode */
                $tableNode = $tgItem['table'];
                $tableType = $this->getTableType($tableNode);

                $tableNode->filter('tbody tr')->each(
                    function (Crawler $rowNode) use (
                        &$parameters,
                        $tableType,
                        $itemName
                    ) {
                        if (self::TABLE_TYPE_TWO !== $tableType) {
                            throw new ParseError("Unexpected table structure: {$itemName}");
                        }

                        $textType = $rowNode->filter('td:nth-child(2)')->text();
                        $textRequired = $rowNode->filter('td:nth-child(3)')->text();

                        $parameters[] = [
                            'name' => $rowNode->filter('td:nth-child(1)')->text(),
                            'type' => $this->parseType($textType),
                            'required' => $this->parseIsRequired($textRequired),
                            'description' => $rowNode->filter('td:nth-child(4)')->text(),
                        ];
                    }
                );
            }

            $description = implode("\n", $tgItem['descriptions']);

            $this->schema['methods'][] = [
                'name' => $itemName,
                'description' => $description,
                'link' => $this->makeLinkForSchema($tgItem['link']),
                'parameters' => $parameters,
                'return' => $this->getReturnType($description),
            ];
        }
    }

    private function getTableType(Crawler $tableNode): int
    {
        $tableColumnNames = [];
        $tableNode->filter('thead th')->each(
            static function (Crawler $colNode) use (&$tableColumnNames) {
                $tableColumnNames[] = $colNode->text();
            }
        );

        if (count($tableColumnNames) === 3
            && array_diff($tableColumnNames, ['Field', 'Type', 'Description']) === []
        ) {
            return self::TABLE_TYPE_ONE;
        }

        if (
            count($tableColumnNames) === 4
            && array_diff($tableColumnNames, ['Parameter', 'Type', 'Required', 'Description']) === []
        ) {
            return self::TABLE_TYPE_TWO;
        }

        throw new ParseError("Unexpected table type: \n{$tableNode->html()}");
    }

    private function makeLinkForSchema(string $link): string
    {
        return strpos($link, 'https') === false ? self::BOT_DOCUMENTATION_URL . $link : $link;
    }

    private function parseType(string $text): array
    {
        if (strpos($text, 'Array of ') !== false) {
            [, $targetType] = explode(' of ', $text);
            $types = $this->parseType($targetType);
            foreach ($types as $index => $type) {
                $types[$index]['is_array'] = true;
            }

            return $types;
        }

        if (strpos($text, ' or ') !== false || strpos($text, ' and ') !== false) {
            $divider = strpos($text, ' or ') !== false ? ' or ' : ' and ';
            $pieces = explode($divider, $text);
            $pieces = array_merge(... array_map(static fn(string $item) => explode(',', $item), $pieces));
            $pieces = array_map(static fn(string $item) => trim($item), $pieces);
            $types = [];
            foreach ($pieces as $piece) {
                $types[] = $this->parseType($piece);
            }

            return array_merge(...$types);
        }

        if ($text === 'Float' || $text === 'Float number') {
            return [['type' => 'float', 'is_array' => false]];
        }

        if ($text === 'Integer' || $text === 'Int') {
            return [['type' => 'int', 'is_array' => false]];
        }

        if ($text === 'True' || $text === 'Boolean') {
            return [['type' => 'bool', 'is_array' => false]];
        }

        if ($text === 'CallbackGame' || $text === 'Array') {
            return [['type' => 'array', 'is_array' => false]];
        }

        if ($text === 'String') {
            return [['type' => 'string', 'is_array' => false]];
        }

        if (isset(Types::ALIAS_TYPES[$text])) {
            return [['type' => $this->getFQCN($text, true), 'is_array' => false]];
        }

        if ($this->isObject($text)) {
            return [['type' => $this->getFQCN($text), 'is_array' => false]];
        }

        throw new ParseError("Unexpected type: {$text}");
    }

    private function getFQCN(string $className, bool $abstract = false): string
    {
        return '\\' . Namespaces::BASE_NAMESPACE_TYPES . '\\' . ($abstract ? 'Abstract' : '') . $className;
    }

    private function isObject(string $type): bool
    {
        if (isset($this->schema['types'][$type])) {
            return true;
        }

        throw new ParseError("Undefined type: {$type}");
    }

    private function getRestrictions(string $text): array
    {
        $arrayRegexps = [
            '/(“(?<enum>\w+)” \([^\(]+\),?\s?)/',
            '/[Oo]ne of (“(?<enum>\w+)”,?\s?(?:or )?)/',
        ];
        $regexps = [
            '/(?<minLength>\d+)\-(?<maxLength>\d+) characters?/',
        ];

        $restrictions = [
            'minLength',
            'maxLength',
        ];
        $arrayRestrictions = [
            'enum',
        ];

        $result = [];
        foreach ($regexps as $regexp) {
            if (preg_match($regexp, $text, $match) === 1) {
                foreach ($restrictions as $restriction) {
                    if (array_key_exists($restriction, $match)) {
                        $result[$restriction] = $match[$restriction];
                    }
                }
            }
        }

        foreach ($arrayRegexps as $regexp) {
            $matchText = $text;
            while (preg_match($regexp, $matchText, $match) === 1) {
                foreach ($arrayRestrictions as $restriction) {
                    if (array_key_exists($restriction, $match)) {
                        $result[$restriction][] = $match[$restriction];
                    }
                }
                $matchText = str_replace($match[1], '', $matchText);
            }
        }

        return $result;
    }

    private function getParent(string $type): string
    {
        foreach (Types::ALIAS_TYPES as $aliasName => $aliases) {
            if (in_array($type, $aliases, true)) {
                return $this->getFQCN($aliasName, true);
            }
        }

        if (ctype_upper($type[0])) {
            return $this->getFQCN(Classes::ABSTRACT_TYPE);
        }

        throw new ParseError("Cannot determine parent of type: {$type}");
    }

    private function parseIsRequired(string $text): bool
    {
        $text = strtolower($text);
        if (in_array($text, [Token::YES, Token::TRUE], true)) {
            return true;
        }
        if (in_array($text, [Token::OPTIONAL, Token::NO], true)) {
            return false;
        }

        throw new ParseError("Unexpected required: {$text}");
    }

    private function getReturnType(string $description): array
    {
        $matchedTypes = [];
        foreach ($this->getRegexps() as $regexp) {
            if (preg_match($regexp, $description, $match) === 1) {
                foreach (['objectName', 'simple'] as $matchType) {
                    if (array_key_exists($matchType, $match)) {
                        // f**k https://core.telegram.org/bots/api#sendmediagroup
                        if (
                            $matchType === 'objectName'
                            && strtolower($match['objectName']) !== strtolower($match['objectAnchor'])
                        ) {
                            $matchType = 'objectAnchor';
                        }

                        foreach ($this->parseType(ucfirst($match[$matchType])) as $type) {
                            $type['is_array'] = array_key_exists('array', $match);
                            $matchedTypes[] = $type;
                        }
                    }
                }
            }
        }

        if (count($matchedTypes) === 0) {
            throw new RuntimeException('return type does not found');
        }

        return $matchedTypes;
    }

    private function getRegexps(): array
    {
        $href = '\<a href\=\".*?\#(?<objectAnchor>.*?)\"\>(?<objectName>.*?)\<\/a\>';
        $em = '\<em\>(?<simple>.*?)\<\/em\>';

        return [
            "/An (?<array>Array) of {$href} objects is returned/",
            "/Returns {$em} on success/",
            "/Returns the new invite link as {$em} on success/",
            "/Returns a {$href}(?: object)?(?: on success)?\./",
            "/Returns the uploaded {$href} on success/",
            "/On success, the sent {$href} is returned/",
            "/On success, an (?<array>array) of the sent {$href} is returned/",
            "/On success, if the edited message was sent by the bot, the edited {$href} is returned, otherwise {$em} is returned/",
            "/On success, if the message was sent by the bot, the sent {$href} is returned, otherwise {$em} is returned/",
            "/On success, a {$href} object is returned/",
            "/On success, returns an (?<array>Array) of {$href} objects/",
            "/On success, {$em} is returned/",
            "/On success, if edited message is sent by the bot, the edited {$href} is returned, otherwise {$em} is returned/",
            "/On success, the stopped {$href} with the final results is returned/",
            '/On success, (?<simple>True) is returned/',
            "/On success, if the message was sent by the bot, returns the edited {$href}, otherwise returns {$em}. Returns an error/",
            "/On success, returns an \<em\>(?<array>Array)\<\/em\> of {$href} objects/",
            "/On success, returns a {$href} object\./",
            "/Returns basic information about the bot in form of a {$href} object\./",
            "/Returns (?<array>Array) of {$href} on success/",
            "/Returns the {$href} of the sent message on success/",
            "/On success, an (?<array>array) of {$href} that were sent is returned/",
            "/On success, if the edited message is not an inline message, the edited {$href} is returned, otherwise {$em} is returned/",
            "/invite link as (?:a )?{$href} object/",
            "/On success, if the message is not an inline message, the edited {$href} is returned, otherwise {$em} is returned./",
            "/On success, the stopped {$href} is returned./",
            "/On success, if the message is not an inline message, the {$href} is returned, otherwise {$em} is returned./",
            "/Returns an (?<array>Array) of {$href} objects/",
            "/Returns {$href} on success/",
            "/Returns the created invoice link as {$em} on success./",
            "/Returns information about the created topic as a $href object./",
            "/On success, an (?<array>array) of $href of the sent messages is returned./",
        ];
    }

}