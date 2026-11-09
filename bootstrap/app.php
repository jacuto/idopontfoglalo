<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . "/../routes/web.php",
        commands: __DIR__ . "/../routes/console.php",
        health: "/up",
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\TenantContext::class,\App\Http\Middleware\MemberPermissions::class]);
        $middleware->prepend(\App\Http\Middleware\EmbedCors::class);
        // Public booking and secret-link management also work in embedded frames.
        $middleware->validateCsrfTokens(
            except: [
                "auth/apple/callback",
                "api/bookings",
                "api/manage",
                "api/manage/checkout",
                "api/stripe/webhook",
                "api/platform-stripe/webhook",
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $r) {
            if($e->getStatusCode()!==429)return null;$seconds=max(1,(int)($e->getHeaders()['Retry-After']??60));
            return response()->json(['error'=>'Túl sok próbálkozás. Próbáld újra '.$seconds.' másodperc múlva.','retryAfter'=>$seconds],429,array_replace($e->getHeaders(),['Retry-After'=>(string)$seconds]));
        });
        $exceptions->render(function (\InvalidArgumentException $e, Request $r) {
            if ($r->is('api/*')) return response()->json(['error' => $e->getMessage()], 422);
        });
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is("api/*") ||
                $request->expectsJson(),
        );
    })
    ->create();
