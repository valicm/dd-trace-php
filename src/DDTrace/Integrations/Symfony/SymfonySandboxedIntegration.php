<?php

namespace DDTrace\Integrations\Symfony;

use DDTrace\Configuration;
use DDTrace\GlobalTracer;
use DDTrace\Integrations\Integration;
use DDTrace\Integrations\SandboxedIntegration;
use DDTrace\SpanData;
use DDTrace\Tag;
use DDTrace\Type;
use Symfony\Component\HttpFoundation\Request;

class SymfonySandboxedIntegration extends SandboxedIntegration
{
    const NAME = 'symfony';

    public function getName()
    {
        self::NAME;
    }

    /**
     * Load the integration
     *
     * @return int
     */
    public function init()
    {
        $tracer = GlobalTracer::get();

        // Create a span that starts from when Symfony first boots
        $scope = $tracer->getRootScope();
        $symfonyRequestSpan = $scope->getSpan();
        $symfonyRequestSpan->overwriteOperationName('symfony.request');
        $appName = Configuration::get()->appName('symfony');

        dd_trace_method('Symfony\Component\HttpKernel\HttpKernel', 'handle', function (SpanData $span, $args, $response) use ($appName) {
            /** @var Request $request */
            list($request) = func_get_args();

            $span->name = 'symfony.kernel.handle';
            $span->service = $appName;
            $span->type = Type::WEB_SERVLET;
            $span->meta[Tag::HTTP_METHOD] = $request->getMethod();
            $span->meta[Tag::HTTP_URL] = $request->getUriForPath($request->getPathInfo());
            $span->meta[Tag::HTTP_STATUS_CODE] = $response->getStatusCode();
            $span->resource = 'dd-error-wile-reading-route';

            $route = $request->get('_route');
            if (null !== $route && null !== $request) {
                $span->resource = $route;
            }
        });

        // Tracing templating engines
        $renderTraceCallback = function (SpanData $span, $args) use ($appName) {
            $span->name = 'symfony.templating.render';
            $span->service = $appName;
            $span->type = Type::WEB_SERVLET;

            $resourceName = count($args) > 0 ? get_class($this) . ' ' . $args[0] : get_class($this);
            $span->resource = $resourceName;
        };
        dd_trace_method('Symfony\Bridge\Twig\TwigEngine', 'render', $renderTraceCallback);
        dd_trace_method('Symfony\Bundle\FrameworkBundle\Templating\TimedPhpEngine', 'render', $renderTraceCallback);
        dd_trace_method('Symfony\Bundle\TwigBundle\TwigEngine', 'render', $renderTraceCallback);
        dd_trace_method('Symfony\Component\Templating\DelegatingEngine', 'render', $renderTraceCallback);
        dd_trace_method('Symfony\Component\Templating\PhpEngine', 'render', $renderTraceCallback);
        dd_trace_method('Twig\Environment', 'render', $renderTraceCallback);
        dd_trace_method('Twig_Environment', 'render', $renderTraceCallback);

        return Integration::LOADED;
    }
}
