<?php

declare(strict_types=1);

namespace MadmagesTelegram\TypesGenerator\Twig;

use Closure;
use MadmagesTelegram\TypesGenerator\Dictionary\Classes;
use RuntimeException;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Extension extends AbstractExtension
{

    private CamelCaseToSnakeCaseNameConverter $converter;

    public function __construct()
    {
        $this->converter = new CamelCaseToSnakeCaseNameConverter();
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('camelize', [$this, 'camelize']),
            new TwigFilter('paramDescription', [$this, 'paramDescription']),
            new TwigFilter('sortByOptional', [$this, 'sortByOptional']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'renderType', function (array $types, string $namespace, bool $isForDoc) {
                return $this->getRenderedType($types, $namespace, $isForDoc);
            }
            ),
            new TwigFunction(
                'isValidCodeType', function (array $type) {
                return $this->getRenderedType($type, '', false) !== null;
            }
            ),
            new TwigFunction('getClassName', Closure::fromCallable([$this, 'getClassName'])),
            new TwigFunction('renderJMSType', Closure::fromCallable([$this, 'renderJMSType'])),
            new TwigFunction('getJMSReturnType', Closure::fromCallable([$this, 'getJMSReturnType'])),
        ];
    }

    private function getRenderedType(array $types, string $namespace, bool $isForDoc): ?string
    {
        if (!$isForDoc && count($types) > 1) {
            $isAllArrays = true;
            foreach ($types as $item) {
                if (!$item['is_array']) {
                    $isAllArrays = false;
                    break;
                }
            }

            if ($isAllArrays) {
                return 'array';
            }

            return null;
        }

        $compiled = [];
        foreach ($types as $item) {
            if (!$this->isSimpleType($item['type'])) {
                $item['type'] = $this->getClassName($item['type'], $namespace);
            }

            if (!$isForDoc && $item['is_array']) {
                return 'array';
            }

            $compiled[] = $item['type'] . ($item['is_array'] ? '[]' : '');
        }

        return implode('|', $compiled);
    }

    private function isSimpleType(string $type): bool
    {
        return in_array($type, ['int', 'string', 'float', 'object', 'array', 'bool'], true);
    }

    private function getClassName(string $typeName, string $namespace, bool $forCode = false)
    {
        $typeName = trim($typeName, '\\');
        $namespace = trim($namespace, '\\');

        $shortClassName = str_replace("{$namespace}\\", '', $typeName);
        if ($forCode) {
            return "{$shortClassName}::class";
        }

        return $shortClassName;
    }

    public function camelize($string)
    {
        return $this->converter->denormalize($string);
    }

    public function paramDescription($description, $indentation, $baseIndentation = '     '): string
    {
        $paragraphs = explode("\n", $description);
        $words = [];
        foreach ($paragraphs as $paragraph) {
            $words[] = explode(' ', $paragraph);
        }
        $words = array_merge(...$words);

        $newDescription = '';
        $characterCount = $indentation;
        foreach ($words as $word) {
            $word = str_replace('@', '@|', $word);
            $characterCount += strlen($word);
            if ($characterCount > 100) {
                $newDescription .= "\n{$baseIndentation}* " . $word . ' ';
                $characterCount = $indentation;
            } else {
                $newDescription .= $word . ' ';
            }
        }

        return $newDescription;
    }

    public function sortByOptional(array $parameters): array
    {
        usort(
            $parameters,
            static function (array $a, array $b) {
                return $b['required'] - $a['required'];
            }
        );

        return $parameters;
    }

    private function getJMSReturnType(array $types, string $namespace): array
    {
        $result = [];
        foreach ($types as $type) {
            $resultItem = $type['type'];
            if ($this->isSimpleType($resultItem)) {
                if ($type['is_array']) {
                    $result[] = "'array<{$resultItem}>'";
                } else {
                    $result[] = "'{$resultItem}'";
                }
            } else {
                $resultItem = $this->getClassName($resultItem, $namespace, true);
                if ($type['is_array']) {
                    $result[] = "'array<' . {$resultItem} . '>'";
                } else {
                    $result[] = $resultItem;
                }
            }
        }

        return $result;
    }

    private function renderJMSType(array $types): string
    {
        if (count($types) > 1) {
            if (count($types) === 2 && strpos($types[0]['type'], Classes::INPUT_FILE) !== false) {
                return 'string';
            }

            return 'string';
        }

        $type = trim($types[0]['type'], '\\');
        if ($types[0]['is_array']) {
            return "array<{$type}>";
        }

        return $type;
    }
}