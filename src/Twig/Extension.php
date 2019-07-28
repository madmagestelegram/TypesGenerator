<?php

namespace MadmagesTelegram\TypesGenerator\Twig;

use RuntimeException;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class Extension extends \Twig_Extension
{
    /**
     * @var CamelCaseToSnakeCaseNameConverter
     */
    private $converter;

    /**
     * Extension constructor.
     */
    public function __construct()
    {
        $this->converter = new CamelCaseToSnakeCaseNameConverter();
    }

    public function getFilters(): array
    {
        return [
            new \Twig_Filter('camelize', [$this, 'camelize']),
            new \Twig_Filter('paramDescription', [$this, 'paramDescription']),
            new \Twig_Filter('sortByOptional', [$this, 'sortByOptional']),
        ];
    }

    public function getFunctions()
    {
        return [
            new \Twig_Function('renderType', function (array $types, string $namespace, bool $isForDoc) {
                return $this->getRenderedType($types, $namespace, $isForDoc);
            }), new \Twig_Function('isValidCodeType', function (array $type) {
                return $this->getRenderedType($type, '', false) !== null;
            }), new \Twig_Function('getClassName', function (string $class, string $namespace) {
                return $this->getClassName($class, $namespace);
            }), new \Twig_Function('renderJMSType', function (array $type) {
                return $this->renderJMSType($type);
            }), new \Twig_Function('getJMSReturnType', function (array $type, string $namespace) {
                return $this->getJMSReturnType($type, $namespace);
            }),
        ];
    }

    private function getJMSReturnType(array $types, string $namespace): array
    {
        $result = [];
        foreach ($types as $type) {
            $resultItem = $type[0];
            if ($this->isSimpleType($resultItem)) {
                if ($type[1]) {
                    $result[] = "'array<{$resultItem}>'";
                } else {
                    $result[] = "'{$resultItem}'";
                }
            } else {
                $resultItem = $this->getClassName($resultItem, $namespace, true);
                if ($type[1]) {
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
            if (count($types) === 2 && strpos($types[0][0], 'AbstractInputFile') !== false) {
                return 'string';
            }

            throw new RuntimeException('Unreachable JMS type');
        }

        $type = trim($types[0][0], '\\');
        if ($types[0][1]) {
            return "array<{$type}>";
        }

        return $type;
    }

    private function getRenderedType(array $types, string $namespace, bool $isForDoc): ?string
    {
        if (!$isForDoc && count($types) > 1) {
            return null;
        }

        $compiled = [];
        foreach ($types as $item) {
            if (!$this->isSimpleType($item[0])) {
                $item[0] = $this->getClassName($item[0], $namespace);
            }

            if (!$isForDoc && $item[1]) {
                return 'array';
            }

            $compiled[] = $item[0] . ($item[1] ? '[]' : '');
        }

        return implode('|', $compiled);
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

    private function isSimpleType(string $type): bool
    {
        return in_array($type, ['int', 'string', 'float', 'object', 'array', 'bool'], true);
    }

    public function camelize($string)
    {
        return $this->converter->denormalize($string);
    }

    public function paramDescription($description, $indentation, $baseIndentation = '     ')
    {
        $paragraphs = explode("\n", $description);
        $words = [];
        foreach ($paragraphs as $paragraph) {
            $words = array_merge($words, explode(' ', $paragraph));
        }

        $newDescription = '';
        $characterCount = $indentation;
        foreach ($words as $word) {
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

    public function sortByOptional($parameters)
    {
        usort($parameters, function ($a, $b) {
            return $b['required'] - $a['required'];
        });

        return $parameters;
    }
}