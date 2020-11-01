<?php declare(strict_types=1);

namespace MadmagesTelegram\TypesGenerator\Command;

use MadmagesTelegram\TypesGenerator\Dictionary\Classes;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Twig\Environment;

class GenerateClientCommand extends Command
{

    public const BASE_NAMESPACE = 'MadmagesTelegram\\Types';
    public const BASE_NAMESPACE_TYPES = self::BASE_NAMESPACE . '\\Type';

    private ContainerBagInterface $parameterBag;
    private Environment $twig;

    public function __construct(ContainerBagInterface $parameterBag, Environment $twig)
    {
        $this->parameterBag = $parameterBag;
        parent::__construct();
        $this->twig = $twig;
    }

    protected function configure(): void
    {
        $this->setName('generate:client');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws \JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $buildDirSource = $this->parameterBag->get('kernel.root_dir') . '/../build/source/';
        $jsonPath = $buildDirSource . 'schema.json';
        $schema = file_get_contents($jsonPath);
        $schema = json_decode($schema, true, 512, JSON_THROW_ON_ERROR);

        $buildDir = $this->parameterBag->get('kernel.root_dir') . '/../build';
        $baseDir = $buildDir . '/' . str_replace('\\', '/', self::BASE_NAMESPACE);
        $baseDirTypes = $buildDir . '/' . str_replace('\\', '/', self::BASE_NAMESPACE_TYPES);

        if (
            (is_dir($baseDirTypes) === false)
            && !mkdir($concurrentDirectory = $baseDirTypes, 0777, true)
            && !is_dir($concurrentDirectory)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $types = [
            [
                Classes::INLINE_QUERY_RESULT,
                ['namespace' => self::BASE_NAMESPACE_TYPES, 'class' => Classes::INLINE_QUERY_RESULT, 'parent' => Classes::ABSTRACT_TYPE],
                'AbstractSimpleClass',
            ],
            [
                Classes::INPUT_MESSAGE_CONTENT,
                ['namespace' => self::BASE_NAMESPACE_TYPES, 'class' => Classes::INPUT_MESSAGE_CONTENT, 'parent' => Classes::ABSTRACT_TYPE],
                'AbstractSimpleClass',
            ],
            [
                Classes::INPUT_FILE,
                ['namespace' => self::BASE_NAMESPACE_TYPES, 'class' => Classes::INPUT_FILE, 'parent' => Classes::ABSTRACT_TYPE],
            ],
            [
                Classes::INPUT_MEDIA,
                ['namespace' => self::BASE_NAMESPACE_TYPES, 'class' => Classes::INPUT_MEDIA, 'parent' => Classes::ABSTRACT_TYPE],
                'AbstractSimpleClass',
            ],
            [
                Classes::PASSPORT_ERROR,
                ['namespace' => self::BASE_NAMESPACE_TYPES, 'class' => Classes::PASSPORT_ERROR, 'parent' => Classes::ABSTRACT_TYPE],
                'AbstractSimpleClass',
            ],
            [
                Classes::ABSTRACT_TYPE,
                ['namespace' => self::BASE_NAMESPACE_TYPES],
            ],
        ];

        $clients = [
            [
                'TypedClient',
                ['namespace' => self::BASE_NAMESPACE, 'schema' => $schema],
            ],
            [
                'Client',
                ['namespace' => self::BASE_NAMESPACE, 'schema' => $schema],
            ],
        ];

        foreach ($types as $type) {
            $this->generate($baseDirTypes, ...$type);
        }

        foreach ($clients as $type) {
            $this->generate($baseDir, ...$type);
        }

        foreach ($schema['types'] as $type) {
            $data = [
                $baseDirTypes,
                $type['name'],
                [
                    'type' => $type,
                    'namespace' => self::BASE_NAMESPACE_TYPES,
                ],
                'Type',
            ];

            $this->generate(...$data);
        }
    }

    private function generate(string $basePath, string $type, array $data = [], string $template = null): void
    {
        $templateFile = $template ?? $type;
        $filePath = $basePath . "/{$type}.php";

        $content = $this->twig->render("{$templateFile}.twig", $data);

        if (file_put_contents($filePath, $content) === false) {
            throw new RuntimeException(sprintf('Failed write to file %s', $filePath));
        }
    }
}