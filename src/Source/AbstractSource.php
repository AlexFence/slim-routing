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

namespace Jgut\Slim\Routing\Source;

/**
 * Abstract routing source.
 */
abstract class AbstractSource implements SourceInterface
{
    /**
     * Sources.
     *
     * @var string[]
     */
    protected $paths;

    /**
     * Source constructor.
     *
     * @param string[] $paths
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaths()
    {
        return $this->paths;
    }
}
