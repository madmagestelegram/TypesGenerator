<?php declare( strict_types=1 );

namespace MadmagesTelegram\TypesGenerator\Command;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateClientCommand extends ContainerAwareCommand
{

    public const BASE_NAMESPACE = 'MadmagesTelegram\\Types';
    public const BASE_NAMESPACE_TYPES = self::BASE_NAMESPACE . '\\Type';

    protected function configure(): void
    {
        $this->setName('generate:client');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $buildDirSource = $this->getContainer()->getParameter('kernel.root_dir') . '/../build/source/';
        $jsonPath = $buildDirSource . 'schema.json';
        $schema = file_get_contents($jsonPath);
        $schema = json_decode($schema, true);

        $buildDir = $this->getContainer()->getParameter('kernel.root_dir') . '/../build';
        $baseDir = $buildDir . '/' . str_replace('\\', '/', self::BASE_NAMESPACE);
        $baseDirTypes = $buildDir . '/' . str_replace('\\', '/', self::BASE_NAMESPACE_TYPES);

        if (
            ( is_dir($baseDirTypes) === false )
            && !mkdir($concurrentDirectory = $baseDirTypes, 0777, true)
            && !is_dir($concurrentDirectory)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $types = [
            [
                'AbstractInlineQueryResult',
                ['namespace' => self::BASE_NAMESPACE_TYPES, 'class' => 'AbstractInlineQueryResult'],
                'AbstractSimpleClass',
            ], [
                'AbstractInputMessageContent',
                ['namespace' => self::BASE_NAMESPACE_TYPES, 'class' => 'AbstractInputMessageContent'],
                'AbstractSimpleClass',
            ], [
                'AbstractInputFile',
                ['namespace' => self::BASE_NAMESPACE_TYPES, 'class' => 'AbstractInputFile'],
            ], [
                'AbstractType',
                ['namespace' => self::BASE_NAMESPACE_TYPES, 'class' => 'AbstractType'],
                'AbstractSimpleClass',
            ], [
                'AbstractInputMedia',
                ['namespace' => self::BASE_NAMESPACE_TYPES, 'class' => 'AbstractInputMedia'],
                'AbstractSimpleClass',
            ], [
                'AbstractType',
                ['namespace' => self::BASE_NAMESPACE_TYPES]
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
                    'type'      => $type,
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

        $content = $this->getContainer()->get('twig')->render("{$templateFile}.php.twig", $data);

        if (file_put_contents($filePath, $content) === false) {
            throw new RuntimeException(sprintf('Failed write to file %s', $filePath));
        }
    }
}