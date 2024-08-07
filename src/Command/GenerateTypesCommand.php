<?php declare(strict_types=1);

namespace MadmagesTelegram\TypesGenerator\Command;

use Exception;
use JsonException;
use MadmagesTelegram\TypesGenerator\Dictionary\Classes;
use MadmagesTelegram\TypesGenerator\Dictionary\File;
use MadmagesTelegram\TypesGenerator\Dictionary\Namespaces;
use MadmagesTelegram\TypesGenerator\Dictionary\TemplateFile;
use MadmagesTelegram\TypesGenerator\Dictionary\Types;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;
use function json_decode;

class GenerateTypesCommand extends Command
{
    private Environment $twig;
    private KernelInterface $kernel;

    public function __construct(KernelInterface $kernel, Environment $twig)
    {
        $this->kernel = $kernel;
        $this->twig = $twig;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate:types')
            ->addOption(
                'schema',
                's',
                InputOption::VALUE_OPTIONAL,
                'Target schema path',
                $this->getSchemaPath()
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Output directory path',
                $this->getBuildDirectoryPath()
            );
    }

    private function getSchemaPath(): string
    {
        return $this->getBuildDirectoryPath() . File::DEFAULT_SCHEMA_NAME;
    }

    private function getBuildDirectoryPath(): string
    {
        return $this->kernel->getProjectDir() . '/var/build/';
    }

    /**
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema = $this->readJsonFile($input->getOption('schema'));

        [$baseDirectory, $baseDirectoryTypes] = $this->createDirectories($input->getOption('output'));

        $output->writeln("Destination directory: {$baseDirectory}");
        $output->writeln('Generating...');

        foreach ($this->getDefaultTypes() as $type) {
            $this->generate($baseDirectoryTypes, ...$type);
        }
        $output->writeln('  -> Default types');

        foreach ($this->getDefaultClients($schema) as $type) {
            $this->generate($baseDirectory, ...$type);
        }
        $output->writeln('  -> Default clients');

        foreach ($this->getDefaultUtils() as $type) {
            $this->generate($baseDirectory, ...$type);
        }
        $output->writeln('  -> Utils');

        foreach ($schema['types'] as $type) {
            $this->generate(
                $baseDirectoryTypes,
                $type['name'],
                [
                    'type' => $type,
                    'namespace' => Namespaces::BASE_NAMESPACE_TYPES,
                ],
                TemplateFile::CUSTOM_TEMPLATE_FOR_TYPE[$type['name']] ?? TemplateFile::TYPE
            );
        }
        $output->writeln("  -> Types: " . count($schema['types']));

        return 0;
    }

    /**
     * @throws JsonException
     */
    private function readJsonFile(string $jsonFilePath): array
    {
        if (!is_file($jsonFilePath)) {
            throw new RuntimeException("File not exists: {$jsonFilePath}");
        }

        $jsonString = file_get_contents($jsonFilePath);
        if ($jsonString === false) {
            throw new RuntimeException("Failed to read file: {$jsonFilePath}");
        }

        return json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
    }

    private function createDirectories(string $root): array
    {
        $buildDirectory = rtrim($root, '/');
        $baseDir = $buildDirectory . '/' . str_replace('\\', '/', Namespaces::BASE_NAMESPACE);
        $baseDirTypes = $buildDirectory . '/' . str_replace('\\', '/', Namespaces::BASE_NAMESPACE_TYPES);

        if (
            is_dir($baseDirTypes) === false
            && !mkdir($baseDirTypes, 0777, true)
            && !is_dir($baseDirTypes)
        ) {
            throw new RuntimeException("Can`t create directory: {$baseDirTypes}");
        }

        return [$baseDir, $baseDirTypes];
    }

    private function getDefaultTypes(): array
    {
        $abstractClasses = [];
        foreach (array_keys(Types::ALIAS_TYPES) as $type) {
            $abstractType = 'Abstract' . $type;
            $abstractClasses[] = [
                $abstractType,
                [
                    'namespace' => Namespaces::BASE_NAMESPACE_TYPES,
                    'class' => $abstractType,
                    'parent' => Classes::ABSTRACT_TYPE,
                ],
                TemplateFile::ABSTRACT_SIMPLE_TYPE,
            ];
        }

        return [
            [
                Classes::ABSTRACT_TYPE,
                ['namespace' => Namespaces::BASE_NAMESPACE_TYPES],
            ],
            ...$abstractClasses,
        ];
    }

    private function generate(string $basePath, string $type, array $data = [], string $customTemplate = null): void
    {
        $templateFile = $customTemplate ?? $type;
        $filePath = "{$basePath}/{$type}.php";

        $content = $this->twig->render("{$templateFile}.twig", $data);

        if (file_put_contents($filePath, $content) === false) {
            throw new RuntimeException("Failed write to file {$filePath}");
        }
    }

    private function getDefaultClients(array $schema): array
    {
        return [
            [
                TemplateFile::TYPED_CLIENT,
                [
                    'namespace' => Namespaces::BASE_NAMESPACE,
                    'schema' => $schema,
                    'exception' => Classes::TELEGRAM_EXCEPTION,
                ],
            ],
            [
                TemplateFile::CLIENT,
                [
                    'namespace' => Namespaces::BASE_NAMESPACE,
                    'schema' => $schema,
                    'exception' => Classes::TELEGRAM_EXCEPTION,
                ],
            ],
        ];
    }

    private function getDefaultUtils(): array
    {
        return [
            [TemplateFile::SERIALIZER, ['namespace' => Namespaces::BASE_NAMESPACE]],
            [
                Classes::TELEGRAM_EXCEPTION,
                [
                    'namespace' => Namespaces::BASE_NAMESPACE,
                    'class' => Classes::TELEGRAM_EXCEPTION,
                    'parent' => '\\' . Exception::class,
                ],
                TemplateFile::SIMPLE_CLASS,
            ],
        ];
    }
}