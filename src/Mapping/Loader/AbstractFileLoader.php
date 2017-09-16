<?php

/*
 * slim-routing (https://github.com/juliangut/slim-routing).
 * Slim framework routing.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/slim-routing
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

declare(strict_types=1);

namespace Jgut\Slim\Routing\Mapping\Loader;

/**
 * Abstract file mapping loader.
 */
abstract class AbstractFileLoader implements FileLoaderInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     */
    public function getMappingData(array $loadingPaths): array
    {
        $mappingData = [];

        foreach ($loadingPaths as $path) {
            if (is_dir($path)) {
                $mappingData[] = $this->loadFromDirectory($path);
            } elseif (is_file($path)) {
                $mappingData[] = [$this->loadFile($path)];
            } else {
                throw new \RuntimeException(sprintf('Path "%s" does not exist', $path));
            }
        }

        $mappingData = count($mappingData) ? array_merge(...$mappingData) : [];

        $routingData = [];
        foreach ($mappingData as $data) {
            $routingData = $this->merge($routingData, $data);
        }

        return $routingData;
    }

    /**
     * Load routing data from directory.
     *
     * @param string $directory
     *
     * @return array
     */
    protected function loadFromDirectory(string $directory): array
    {
        $mappingData = [];

        $filePattern = sprintf('/^.+\.%s$/i', $this->getExtension());
        $recursiveIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $regexIterator = new \RegexIterator($recursiveIterator, $filePattern, \RecursiveRegexIterator::GET_MATCH);

        foreach ($regexIterator as $file) {
            $mappingData[] = $this->loadFile($file[0]);
        }

        return $mappingData;
    }

    /**
     * Get routing extension.
     *
     * @return string
     */
    abstract protected function getExtension(): string;

    /**
     * Load routing data from file.
     *
     * @param string $file
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    abstract protected function loadFile(string $file): array;

    /**
     * Merge arrays.
     *
     * @param array $arrayA
     * @param array $arrayB
     *
     * @return array
     */
    final protected function merge(array $arrayA, array $arrayB): array
    {
        foreach ($arrayB as $key => $value) {
            if (isset($arrayA[$key]) || array_key_exists($key, $arrayA)) {
                if (is_int($key)) {
                    $arrayA[] = $value;
                } elseif (is_array($value) && is_array($arrayA[$key])) {
                    $arrayA[$key] = $this->merge($arrayA[$key], $value);
                } else {
                    $arrayA[$key] = $value;
                }
            } else {
                $arrayA[$key] = $value;
            }
        }

        return $arrayA;
    }
}