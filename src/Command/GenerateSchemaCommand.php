<?php

declare(strict_types=1);

namespace MadmagesTelegram\TypesGenerator\Command;

use JsonException;
use MadmagesTelegram\TypesGenerator\Dictionary\Classes;
use MadmagesTelegram\TypesGenerator\Dictionary\Types;
use ParseError;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DomCrawler\Crawler;

class GenerateSchemaCommand extends Command
{

    /** @var string */
    private const BOT_DOCUMENTATION_URL = 'https://core.telegram.org/bots/api';

    private const TABLE_TYPE_ONE = 1;
    private const TABLE_TYPE_TWO = 2;

    private array $schema;
    private ContainerBagInterface $parameterBag;

    public function __construct(ContainerBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('generate:schema');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $buildDirSource = $this->parameterBag->get('kernel.root_dir') . '/../build/source/';
        $htmlPath = $buildDirSource . 'schema.html';
        $jsonPath = $buildDirSource . 'schema.json';

        if (!file_exists($htmlPath)) {
            $contents = file_get_contents(self::BOT_DOCUMENTATION_URL);
            file_put_contents($htmlPath, $contents);
        }

        $html = file_get_contents($htmlPath);

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
            ->each(function (Crawler $pageNode) use (&$tgItems, &$methodOrTypeName, &$previousNodeName, &$isStarted) {
                if (
                    !$isStarted
                    && $pageNode->nodeName() === 'h3'
                    && stripos($pageNode->text(), 'Getting updates') === 0
                ) {
                    $isStarted = true;

                    return;
                }

                if (!$isStarted) {
                    return;
                }

                if ($pageNode->nodeName() === 'h4') {
                    $methodOrTypeName = $pageNode->text();
                    $tgItems[$methodOrTypeName]['isType'] = ctype_upper($methodOrTypeName[0]);
                    $tgItems[$methodOrTypeName]['link'] = $pageNode->filter('a')->attr('href');
                }

                if (!empty($methodOrTypeName) && in_array($previousNodeName, ['h4', 'p', 'blockquote'], true)) {
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
            });

        foreach ($tgItems as $itemName => $tgItem) {
            if (
                !$tgItem['isType']
                || array_key_exists($itemName, Types::ALIAS_TYPES)
                || in_array($itemName, Types::SKIP_TYPES, true)
            ) {
                continue;
            }

            if (!isset($tgItem['table'])) {
                throw new ParseError("Expecting table in type description: {$itemName}");
            }

            $fields = [];
            /** @var Crawler $tableNode */
            $tableNode = $tgItem['table'];

            $tableType = $this->getTableType($tableNode);
            $tableNode->filter('tbody tr')->each(function (Crawler $rowNode) use (&$fields, $tableType) {
                $required = null;
                $description = $rowNode->filter('td:nth-child(3)')->text();
                if ($tableType === self::TABLE_TYPE_ONE) {
                    $required = stripos($description, 'optional') === false;
                }

                if ($tableType === self::TABLE_TYPE_TWO) {
                    $required = stripos($rowNode->filter('td:nth-child(4)')->text(), 'optional') === false;
                }

                if ($required === null) {
                    throw new RuntimeException('Expecting $required');
                }

                $fields[] = [
                    'name' => $rowNode->filter('td:nth-child(1)')->text(),
                    'roughType' => $rowNode->filter('td:nth-child(2)')->text(),
                    'description' => $description,
                    'required' => $required,
                ];
            });

            $this->schema['types'][$itemName] = [
                'name' => $itemName,
                'link' => $tgItem['link'],
                'descriptions' => $tgItem['descriptions'],
                'fields' => $fields,
            ];
        }

        foreach ($this->schema['types'] as &$type) {
            foreach ($type['fields'] as &$field) {
                $field['type'] = $this->parseType($field['roughType']);
                unset($field['roughType']);
            }
            unset($field);

            $type['parent'] = $this->getParent($type['name']);
        }
        unset($type);

        foreach ($tgItems as $itemName => $tgItem) {
            if ($tgItem['isType']) {
                continue;
            }

            $parameters = [];
            if (isset($tgItem['table'])) {
                /** @var Crawler $tableNode */
                $tableNode = $tgItem['table'];
                $tableType = $this->getTableType($tableNode);

                $tableNode->filter('tbody tr')->each(function (Crawler $rowNode) use (
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
                        'required' => $this->parseRequired($textRequired),
                        'description' => $rowNode->filter('td:nth-child(4)')->text(),
                    ];
                });
            }

            $description = implode("\n", $tgItem['descriptions']);

            $this->schema['methods'][] = [
                'name' => $itemName,
                'description' => $description,
                'link' => $tgItem['link'],
                'parameters' => $parameters,
                'return' => $this->getReturnType($description),
            ];
        }

        file_put_contents($jsonPath, json_encode($this->schema, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo realpath($jsonPath);
    }

    private function getTableType(Crawler $tableNode): int
    {
        $tableColumnNames = [];
        $tableNode->filter('thead th')->each(static function (Crawler $colNode) use (&$tableColumnNames) {
            $tableColumnNames[] = $colNode->text();
        });

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

        throw new ParseError('Unexpected table type');
    }


    private function parseType(string $text)
    {
        if (strpos($text, 'Array of ') !== false) {
            [, $text] = explode(' of ', $text);
            $types = $this->parseType($text);
            foreach ($types as &$type) {
                $type[1] = true;
            }

            return $types;
        }

        if (strpos($text, ' or ') !== false || strpos($text, ' and ') !== false) {
            $divider = strpos($text, ' or ') !== false ? ' or ' : ' and ';
            $pieces = explode($divider, $text);
            $types = [];
            foreach ($pieces as $piece) {
                $types[] = $this->parseType($piece);
            }

            return array_merge(...$types);
        }

        if ($text === 'Float' || $text === 'Float number') {
            return [['float', false]];
        }

        if ($text === 'Integer' || $text === 'Int') {
            return [['int', false]];
        }

        if ($text === 'True' || $text === 'Boolean') {
            return [['bool', false]];
        }

        if ($text === 'CallbackGame' || $text === 'Array') {
            return [['array', false]];
        }

        if ($text === 'String' || $text === 'Integer or String') {
            return [['string', false]];
        }

        if ($text === 'Array of String') {
            return [['string', true]];
        }

        if (array_key_exists($text, Types::PARENT_ALIAS)) {
            return [[$this->getClassName(Types::PARENT_ALIAS[$text]), false]];
        }

        if (array_key_exists($text, Types::ALIAS_TYPES)) {
            return array_map(
                fn($type) => [$type, false],
                array_map([$this, 'getClassName'], Types::ALIAS_TYPES[$text])
            );
        }

        if ($this->isObject($text)) {
            return [[$this->getClassName($text), false]];
        }

        throw new ParseError("Unexpected type: {$text}");
    }

    private function getClassName(string $className): string
    {
        return '\\' . GenerateClientCommand::BASE_NAMESPACE_TYPES . '\\' . $className;
    }

    /**
     * @param $text
     * @return bool
     */
    private function isObject(string $text): bool
    {
        if (isset($this->schema['types'][$text])) {
            return true;
        }

        throw new ParseError("Undefined type: {$text}");
    }

    private function getParent(string $type): string
    {
        if (array_key_exists($type, Types::PARENT_ALIAS)) {
            return $this->getClassName(Types::PARENT_ALIAS[$type]);
        }

        foreach (Types::ALIAS_TYPES as $aliasName => $aliases) {
            if (array_key_exists($aliasName, Types::PARENT_ALIAS) && in_array($type, $aliases, true)) {
                return $this->getClassName(Types::PARENT_ALIAS[$aliasName]);
            }
        }

        if (strpos($type, 'InlineQueryResult') === 0) {
            return $this->getClassName(Classes::INLINE_QUERY_RESULT);
        }

        if (strpos($type, 'Input') === 0 && strpos($type, 'MessageContent') !== false) {
            return $this->getClassName('AbstractInputMessageContent');
        }

        if (ctype_upper($type[0])) {
            return $this->getClassName('AbstractType');
        }

        throw new ParseError("Cannot determine parent of type: {$type}");
    }

    private function parseRequired(string $text): bool
    {
        if (in_array($text, ['Yes', 'True'], true)) {
            return true;
        }
        if (in_array($text, ['Optional', 'No'], true)) {
            return false;
        }

        throw new ParseError('Unexpected required: ' . $text);
    }

    private function getReturnType(string $description): array
    {
        $matchedTypes = [];
        foreach ($this->getRegexps() as $regexp) {
            if (preg_match($regexp, $description, $match) === 1) {
                foreach (['objectName', 'simple'] as $matchType) {
                    if (array_key_exists($matchType, $match)) {
                        // f**k https://core.telegram.org/bots/api#sendmediagroup
                        if ($matchType === 'objectName' && strtolower($match['objectName']) !== strtolower($match['objectAnchor'])) {
                            $matchType = 'objectAnchor';
                        }
                        foreach ($this->parseType(ucfirst($match[$matchType])) as $type) {
                            $type[1] = array_key_exists('array', $match);
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
        ];
    }

}