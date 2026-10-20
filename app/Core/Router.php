<?php

declare(strict_types=1);

namespace Sibol\Core;

/**
 * Small router with route groups and middleware names:
 *   'auth'             signed-in, active account
 *   'guest'            signed-out only
 *   'role:a,b'         account role must be one of the list
 *   any other name     a guard registered with guard(), run in order after the checks above
 * CSRF is verified for every POST in App, not per route.
 */
final class Router
{
    /** @var list<array{method:string,pattern:string,regex:string,names:list<string>,handler:array,middleware:list<string>}> */
    private array $routes = [];
    private string $prefix = '';
    /** @var list<string> */
    private array $middleware = [];
    /** @var array<string,callable> */
    private array $guards = [];

    /**
     * A named check that routes can list as middleware. It returns a Response to stop the request
     * (for example a redirect), or null to let it through; it may also throw an HttpException.
     */
    public function guard(string $name, callable $check): void
    {
        $this->guards[$name] = $check;
    }

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $define): void
    {
        $prevPrefix = $this->prefix;
        $prevMiddleware = $this->middleware;
        $this->prefix = rtrim($prevPrefix . $prefix, '/');
        $this->middleware = array_merge($prevMiddleware, $middleware);
        $define($this);
        $this->prefix = $prevPrefix;
        $this->middleware = $prevMiddleware;
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $full = '/' . trim($this->prefix . '/' . trim($path, '/'), '/');
        $names = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_]+)\}/', static function (array $m) use (&$names): string {
            $names[] = $m[1];
            $isId = $m[1] === 'id' || str_ends_with($m[1], 'Id');
            return $isId ? '(\d+)' : '([A-Za-z0-9_\-]+)';
        }, $full);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $full,
            'regex' => '#^' . $regex . '$#',
            'names' => $names,
            'handler' => $handler,
            'middleware' => array_merge($this->middleware, $middleware),
        ];
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }
            $args = [];
            foreach ($route['names'] as $i => $name) {
                $value = $m[$i + 1];
                $args[] = ctype_digit($value) ? (int) $value : $value;
                $request->params[$name] = end($args);
            }
            $guard = $this->runMiddleware($route['middleware'], $request);
            if ($guard !== null) {
                return $guard;
            }
            [$class, $method] = $route['handler'];
            $controller = new $class($request);
            $result = $controller->$method(...$args);
            return $result instanceof Response ? $result : Response::html((string) $result);
        }
        if ($pathMatched) {
            throw new HttpException(405, 'That action is not available at this address.');
        }
        throw HttpException::notFound();
    }

    /** @param list<string> $middleware */
    private function runMiddleware(array $middleware, Request $request): ?Response
    {
        foreach ($middleware as $name) {
            if ($name === 'auth' || str_starts_with($name, 'role:')) {
                if (!Auth::check()) {
                    if ($request->wantsJson()) {
                        return Response::json(['error' => t('Your session has ended. Sign in again.')], 401);
                    }
                    Session::set('intended', $request->method === 'GET' ? $request->fullPath() : null);
                    return Response::redirect('/login', 302);
                }
            }
            if ($name === 'guest' && Auth::check()) {
                return Response::redirect(home_path(), 302);
            }
            if (str_starts_with($name, 'role:')) {
                $roles = explode(',', substr($name, 5));
                if (!in_array(Auth::role(), $roles, true)) {
                    Audit::log('access.denied', 'route', null, ['path' => $request->path]);
                    throw HttpException::forbidden();
                }
            }
            if (isset($this->guards[$name])) {
                $stop = ($this->guards[$name])($request);
                if ($stop instanceof Response) {
                    return $stop;
                }
            } elseif ($name !== 'auth' && $name !== 'guest' && !str_starts_with($name, 'role:')) {
                // A misspelt check must not let the request through.
                throw new \LogicException('Unknown route middleware: ' . $name);
            }
        }
        return null;
    }
}
