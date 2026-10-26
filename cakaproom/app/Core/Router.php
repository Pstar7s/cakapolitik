<?php
namespace App\Core;

class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:mixed}> */
    private array $routes = [];
    private static ?string $basePath = null;

    public function get(string $p, $h): void    { $this->add('GET', $p, $h); }
    public function post(string $p, $h): void   { $this->add('POST', $p, $h); }
    public function any(string $p, $h): void    { $this->add('ANY', $p, $h); }

    private function add(string $method, string $pattern, $handler): void
    {
        $regex = preg_replace('#\{([a-z_]+)\}#i', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'regex'   => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    /** Sub-directory the app is installed in, e.g. "" or "/cakaproom". */
    public static function basePath(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }
        $configured = Bootstrap::config('app.base_url');
        if ($configured) {
            $path = parse_url($configured, PHP_URL_PATH) ?: '';
            return self::$basePath = rtrim($path, '/');
        }
        // Derive from the location of index.php
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        return self::$basePath = ($script === '/' ? '' : rtrim($script, '/'));
    }

    public function currentUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'HEAD') { $method = 'GET'; }
        $uri = $this->currentUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $uri, $m)) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->invoke($route['handler'], array_values($params));
            return;
        }

        $this->notFound($uri);
    }

    private function invoke($handler, array $params): void
    {
        if (is_callable($handler)) {
            echo $handler(...$params);
            return;
        }
        // "Controller@method"
        [$class, $action] = explode('@', $handler, 2);
        $fqcn = 'App\\Controllers\\' . $class;
        $controller = new $fqcn();
        echo $controller->$action(...$params);
    }

    private function notFound(string $uri): void
    {
        http_response_code(404);
        if (str_starts_with($uri, '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Endpoint tidak ditemukan'], JSON_UNESCAPED_UNICODE);
            return;
        }
        (new \App\Core\View())->render('errors/404', [], 'layouts/app');
    }
}
