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

namespace Jgut\Slim\Routing\Route;

use Jgut\Slim\Routing\Configuration;
use Jgut\Slim\Routing\Mapping\Metadata\GroupMetadata;
use Jgut\Slim\Routing\Mapping\Metadata\RouteMetadata;
use Jgut\Slim\Routing\Response\Handler\ResponseTypeHandler;
use Jgut\Slim\Routing\Response\ResponseType;
use Jgut\Slim\Routing\Transformer\ParameterTransformer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Handlers\Strategies\RequestResponse;
use Slim\Http\Response;
use Slim\Route as SlimRoute;

/**
 * Response type aware route.
 *
 * @SuppressWarnings(PMD.CouplingBetweenObjects)
 */
class Route extends SlimRoute
{
    /**
     * Routing configuration.
     *
     * @var Configuration
     */
    protected $configuration;

    /**
     * Route metadata.
     *
     * @var RouteMetadata|null
     */
    protected $metadata;

    /**
     * Route constructor.
     *
     * @param string|string[]    $methods
     * @param string             $pattern
     * @param callable           $callable
     * @param Configuration      $configuration
     * @param RouteMetadata|null $metadata
     * @param \Slim\RouteGroup[] $groups
     * @param int                $identifier
     */
    public function __construct(
        $methods,
        string $pattern,
        $callable,
        Configuration $configuration,
        RouteMetadata $metadata = null,
        array $groups = [],
        int $identifier = 0
    ) {
        parent::__construct($methods, $pattern, $callable, $groups, $identifier);

        $this->configuration = $configuration;
        $this->metadata = $metadata;
    }

    /**
     * Get route metadata.
     *
     * @return RouteMetadata|null
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response)
    {
        if ($this->metadata !== null
            && $this->metadata->isXmlHttpRequest()
            && \strtolower($request->getHeaderLine('X-Requested-With')) !== 'xmlhttprequest'
        ) {
            return (new Response(400))->withProtocolVersion($response->getProtocolVersion());
        }

        $this->finalize();

        return $this->callMiddlewareStack($request, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        $dispatchedResponse = $this->dispatchRoute($request, $response);

        if ($dispatchedResponse instanceof ResponseType) {
            $dispatchedResponse = $this->handleResponseType($dispatchedResponse);
        }

        return $dispatchedResponse;
    }

    /**
     * Dispatch route.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     *
     * @throws \Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Throwable
     *
     * @return ResponseInterface|ResponseType
     *
     * @SuppressWarnings(PMD.CyclomaticComplexity)
     * @SuppressWarnings(PMD.NPathComplexity)
     */
    protected function dispatchRoute(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->callable = $this->resolveCallable($this->callable);

        /** @var \Slim\Interfaces\InvocationStrategyInterface $handler */
        $handler = isset($this->container) ? $this->container->get('foundHandler') : new RequestResponse();

        $dispatchedResponse = $handler(
            $this->callable,
            $request,
            $response,
            $this->transformArguments($this->arguments)
        );

        if ($dispatchedResponse instanceof ResponseType
            || $dispatchedResponse instanceof ResponseInterface
        ) {
            $response = $dispatchedResponse;
        } elseif (\is_string($dispatchedResponse)) {
            if ($response->getBody()->isWritable()) {
                $response->getBody()->write($dispatchedResponse);
            }
        }

        return $response;
    }

    /**
     * Transform route arguments.
     *
     * @param array $arguments
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     *
     * @return array
     */
    protected function transformArguments(array $arguments): array
    {
        if ($this->metadata === null) {
            return $arguments;
        }

        $transformer = $this->metadata->getTransformer();
        if (\is_string($transformer) && isset($this->container)) {
            $transformer = $this->container->get($transformer);
        }

        if ($transformer instanceof ParameterTransformer) {
            $arguments = $transformer->transform($arguments, $this->getRouteParameters($this->metadata));
        }

        return $arguments;
    }

    /**
     * Get route parameters.
     *
     * @param RouteMetadata $route
     *
     * @return array
     */
    protected function getRouteParameters(RouteMetadata $route): array
    {
        $parameters = \array_filter(\array_map(
            function (GroupMetadata $group) {
                return $group->getParameters();
            },
            $route->getGroupChain()
        ));
        \array_unshift($parameters, $route->getParameters());

        return \array_filter(\array_merge(...$parameters));
    }

    /**
     * Handle response type.
     *
     * @param ResponseType $responseType
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RuntimeException
     *
     * @return ResponseInterface
     */
    protected function handleResponseType(ResponseType $responseType): ResponseInterface
    {
        $responseHandlers = $this->configuration->getResponseHandlers();
        $type = \get_class($responseType);

        if (!\array_key_exists($type, $responseHandlers)) {
            throw new \RuntimeException(\sprintf('No handler registered for response type "%s"', $type));
        }

        $handler = $responseHandlers[$type];

        if (\is_string($handler) && isset($this->container)) {
            $handler = $this->container->get($handler);
        }

        if (!$handler instanceof ResponseTypeHandler) {
            throw new \RuntimeException(\sprintf(
                'Response handler should implement %s, "%s" given',
                ResponseTypeHandler::class,
                \is_object($handler) ? \get_class($handler) : \gettype($handler)
            ));
        }

        return $handler->handle($responseType);
    }
}
