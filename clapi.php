<?php

namespace CLIGenerator;

use InvalidArgumentException;
use RuntimeException;

/**
 * Universal CLI API Generator
 * Takes API route specifications and generates executable CLI applications
 */

// Core Data Structures
class Parameter
{
    public string $name;
    public string $type;
    public bool $required;
    public ?string $description;

    public function __construct(string $name, string $type, bool $required, ?string $description = null)
    {
        $this->name = $name;
        $this->type = $type;
        $this->required = $required;
        $this->description = $description;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['type'],
            $data['required'] ?? false,
            $data['description'] ?? null
        );
    }
}

class Route
{
    public string $path;
    public string $method;
    public array $parameters;
    public ?string $description;
    public ?string $auth;

    public function __construct(string $path, string $method, array $parameters = [], ?string $description = null, ?string $auth = null)
    {
        $this->path = $path;
        $this->method = strtoupper($method);
        $this->parameters = $parameters;
        $this->description = $description;
        $this->auth = $auth;
    }

    public static function fromArray(array $data): self
    {
        $parameters = array_map([Parameter::class, 'fromArray'], $data['parameters'] ?? []);
        
        return new self(
            $data['path'],
            $data['method'],
            $parameters,
            $data['description'] ?? null,
            $data['auth'] ?? null
        );
    }
}

class AuthConfig
{
    public string $type;
    public ?string $header;

    public function __construct(string $type, ?string $header = null)
    {
        $this->type = $type;
        $this->header = $header;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['type'],
            $data['header'] ?? null
        );
    }
}

class APISpec
{
    public string $name;
    public string $baseUrl;
    public array $routes;
    public ?AuthConfig $auth;

    public function __construct(string $name, string $baseUrl, array $routes, ?AuthConfig $auth = null)
    {
        $this->name = $name;
        $this->baseUrl = $baseUrl;
        $this->routes = $routes;
        $this->auth = $auth;
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        $routes = array_map([Route::class, 'fromArray'], $data['routes'] ?? []);
        $auth = isset($data['auth']) ? AuthConfig::fromArray($data['auth']) : null;

        return new self(
            $data['name'],
            $data['baseUrl'],
            $routes,
            $auth
        );
    }
}

class CLICommand
{
    public string $name;
    public string $description;
    public Route $route;
    public array $args;

    public function __construct(string $name, string $description, Route $route, array $args)
    {
        $this->name = $name;
        $this->description = $description;
        $this->route = $route;
        $this->args = $args;
    }
}

class CLIArg
{
    public string $name;
    public string $type;
    public bool $required;
    public string $flag;

    public function __construct(string $name, string $type, bool $required, string $flag)
    {
        $this->name = $name;
        $this->type = $type;
        $this->required = $required;
        $this->flag = $flag;
    }
}

// CLI Generator Engine
class CLIGenerator
{
    private APISpec $spec;

    public function __construct(APISpec $spec)
    {
        $this->spec = $spec;
    }

    public function routeToCommand(Route $route): CLICommand
    {
        $commandName = $this->pathToCommandName($route->path, $route->method);
        $description = $route->description ?? 'API endpoint';
        $args = array_map([$this, 'paramToArg'], $route->parameters);

        return new CLICommand($commandName, $description, $route, $args);
    }

    private function pathToCommandName(string $path, string $method): string
    {
        $pathParts = array_filter(explode('/', $path));
        $cleanParts = array_map(function ($part) {
            return str_replace(['{', '}'], '', $part);
        }, $pathParts);

        $methodName = match (strtoupper($method)) {
            'GET' => 'get',
            'POST' => 'create',
            'PUT' => 'update',
            'DELETE' => 'delete',
            'PATCH' => 'patch',
            default => strtolower($method)
        };

        return implode(':', array_merge($cleanParts, [$methodName]));
    }

    private function paramToArg(Parameter $param): CLIArg
    {
        return new CLIArg(
            $param->name,
            $param->type,
            $param->required,
            '--' . strtolower(str_replace('_', '-', $param->name))
        );
    }

    public function generatePHPCLI(): string
    {
        $commands = array_map([$this, 'routeToCommand'], $this->spec->routes);
        
        $code = $this->generateHeader();
        $code .= $this->generateConfig();
        $code .= $this->generateAuthFunctions();
        $code .= $this->generateHttpClient();
        $code .= $this->generateCommands($commands);
        $code .= $this->generateMainFunction($commands);
        
        return $code;
    }

    private function generateHeader(): string
    {
        return <<<'PHP'
#!/usr/bin/env php
<?php

/**
 * Auto-generated CLI client for API
 * Generated by Universal CLI API Generator
 */

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.' . PHP_EOL);
}

// Simple HTTP client class
class SimpleHttpClient
{
    private array $headers = [];
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function setHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function request(string $method, string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        
        // Handle query parameters for GET requests
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
            $params = [];
        }

        $context = [
            'http' => [
                'method' => $method,
                'header' => $this->buildHeaders(),
                'content' => !empty($params) ? json_encode($params) : '',
                'ignore_errors' => true
            ]
        ];

        $response = file_get_contents($url, false, stream_context_create($context));
        
        if ($response === false) {
            throw new RuntimeException("Failed to make request to $url");
        }

        $decoded = json_decode($response, true);
        return $decoded ?? ['raw' => $response];
    }

    private function buildHeaders(): string
    {
        $headerStrings = [];
        foreach ($this->headers as $name => $value) {
            $headerStrings[] = "$name: $value";
        }
        
        if (!isset($this->headers['Content-Type'])) {
            $headerStrings[] = 'Content-Type: application/json';
        }
        
        return implode("\r\n", $headerStrings);
    }
}

PHP;
    }

    private function generateConfig(): string
    {
        return sprintf(<<<'PHP'

// Configuration
const BASE_URL = '%s';
const API_NAME = '%s';

PHP, $this->spec->baseUrl, $this->spec->name);
    }

    private function generateAuthFunctions(): string
    {
        if (!$this->spec->auth) {
            return "\n// No authentication configured\n";
        }

        return match ($this->spec->auth->type) {
            'bearer' => <<<'PHP'

function addAuth(SimpleHttpClient $client): void
{
    $token = getenv('API_TOKEN');
    if (!$token) {
        fwrite(STDERR, "Error: API_TOKEN environment variable required\n");
        exit(1);
    }
    $client->setHeader('Authorization', 'Bearer ' . $token);
}

PHP,
            'api-key' => <<<'PHP'

function addAuth(SimpleHttpClient $client): void
{
    $key = getenv('API_KEY');
    if (!$key) {
        fwrite(STDERR, "Error: API_KEY environment variable required\n");
        exit(1);
    }
    $client->setHeader('X-API-Key', $key);
}

PHP,
            default => "\nfunction addAuth(SimpleHttpClient \$client): void { /* No auth */ }\n"
        };
    }

    private function generateHttpClient(): string
    {
        return <<<'PHP'

function createClient(): SimpleHttpClient
{
    $client = new SimpleHttpClient(BASE_URL);
    addAuth($client);
    return $client;
}

function formatOutput(array $data, string $format = 'json'): string
{
    switch ($format) {
        case 'table':
            return formatAsTable($data);
        case 'csv':
            return formatAsCsv($data);
        case 'json':
        default:
            return json_encode($data, JSON_PRETTY_PRINT);
    }
}

function formatAsTable(array $data): string
{
    if (empty($data)) return "No data\n";
    
    // Simple table formatting
    if (isset($data[0]) && is_array($data[0])) {
        $headers = array_keys($data[0]);
        $output = implode("\t", $headers) . "\n";
        $output .= str_repeat("-", strlen($output)) . "\n";
        
        foreach ($data as $row) {
            $values = [];
            foreach ($headers as $header) {
                $values[] = $row[$header] ?? '';
            }
            $output .= implode("\t", $values) . "\n";
        }
        return $output;
    }
    
    return json_encode($data, JSON_PRETTY_PRINT);
}

function formatAsCsv(array $data): string
{
    if (empty($data)) return "";
    
    $output = fopen('php://temp', 'r+');
    
    if (isset($data[0]) && is_array($data[0])) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

PHP;
    }

    private function generateCommands(array $commands): string
    {
        $code = "\n// Generated Commands\n";
        
        foreach ($commands as $command) {
            $code .= $this->generateCommand($command);
        }
        
        return $code;
    }

    private function generateCommand(CLICommand $command): string
    {
        $functionName = 'cmd_' . str_replace([':', '-'], '_', $command->name);
        $method = $command->route->method;
        $path = $command->route->path;
        
        return sprintf(<<<'PHP'

function %s(array $args): void
{
    $params = parseArgs($args);
    $client = createClient();
    
    try {
        // Replace path parameters
        $path = '%s';
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }
        
        $response = $client->request('%s', $path, $params);
        $format = $params['format'] ?? 'json';
        echo formatOutput($response, $format);
    } catch (Exception $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }
}

PHP, $functionName, $path, $method);
    }

    private function generateMainFunction(array $commands): string
    {
        $caseStatements = '';
        $usageLines = '';
        
        foreach ($commands as $command) {
            $functionName = 'cmd_' . str_replace([':', '-'], '_', $command->name);
            $caseStatements .= "        case '{$command->name}':\n";
            $caseStatements .= "            {$functionName}(array_slice(\$argv, 2));\n";
            $caseStatements .= "            break;\n";
            
            $usageLines .= "    echo \"  {$command->name} - {$command->description}\\n\";\n";
        }

        return <<<PHP

function parseArgs(array \$args): array
{
    \$params = [];
    \$i = 0;
    
    while (\$i < count(\$args)) {
        \$arg = \$args[\$i];
        
        if (strpos(\$arg, '--') === 0) {
            \$key = substr(\$arg, 2);
            \$value = \$args[\$i + 1] ?? '';
            \$params[\$key] = \$value;
            \$i += 2;
        } else {
            \$i++;
        }
    }
    
    return \$params;
}

function printUsage(): void
{
    echo "Usage: " . basename(\$_SERVER['argv'][0]) . " <command> [options]\\n";
    echo "\\nAvailable commands:\\n";
{$usageLines}
    echo "\\nGlobal options:\\n";
    echo "  --format <json|table|csv>  Output format (default: json)\\n";
    echo "\\nEnvironment variables:\\n";
    echo "  API_TOKEN  Bearer token for authentication\\n";
    echo "  API_KEY    API key for authentication\\n";
}

// Main execution
if (\$argc < 2) {
    printUsage();
    exit(1);
}

\$command = \$argv[1];

switch (\$command) {
    case 'help':
    case '--help':
    case '-h':
        printUsage();
        break;
{$caseStatements}
    default:
        echo "Unknown command: \$command\\n\\n";
        printUsage();
        exit(1);
}

PHP;
    }

    public function generateComposerJson(): string
    {
        $projectName = strtolower(str_replace(' ', '-', $this->spec->name)) . '-cli';
        
        return json_encode([
            'name' => "generated/{$projectName}",
            'description' => "Auto-generated CLI client for {$this->spec->name} API",
            'type' => 'project',
            'require' => [
                'php' => '>=8.0'
            ],
            'bin' => [$projectName],
            'autoload' => [
                'psr-4' => [
                    'Generated\\' => 'src/'
                ]
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function generateProject(): void
    {
        $projectName = strtolower(str_replace(' ', '-', $this->spec->name)) . '-cli';
        
        echo "Generating CLI project: {$projectName}\n";
        
        // Generate main CLI file
        $cliCode = $this->generatePHPCLI();
        file_put_contents($projectName, $cliCode);
        chmod($projectName, 0755);
        echo "✓ Generated {$projectName}\n";
        
        // Generate composer.json
        $composerJson = $this->generateComposerJson();
        file_put_contents('composer.json', $composerJson);
        echo "✓ Generated composer.json\n";
        
        // Generate README
        $readme = $this->generateReadme($projectName);
        file_put_contents('README.md', $readme);
        echo "✓ Generated README.md\n";
        
        echo "\nProject generated successfully!\n";
        echo "To use:\n";
        echo "  ./{$projectName} help\n";
        echo "  ./{$projectName} <command> [options]\n";
    }

    private function generateReadme(string $projectName): string
    {
        $commands = array_map([$this, 'routeToCommand'], $this->spec->routes);
        $commandList = '';
        
        foreach ($commands as $command) {
            $commandList .= "- `{$command->name}` - {$command->description}\n";
        }

        return <<<MD
# {$this->spec->name} CLI

Auto-generated CLI client for {$this->spec->name} API.

## Installation

Make the CLI executable:
```bash
chmod +x {$projectName}
```

## Usage

```bash
./{$projectName} <command> [options]
```

## Available Commands

{$commandList}

## Authentication

Set the required environment variable:
- `API_TOKEN` - For Bearer token authentication
- `API_KEY` - For API key authentication

## Examples

```bash
# List users
./{$projectName} users:get --limit 10 --format table

# Create a new user
./{$projectName} users:create --name "John Doe" --email "john@example.com"

# Get user by ID
./{$projectName} users:get --id 123
```

## Output Formats

- `json` (default) - Pretty-printed JSON
- `table` - Tabular format
- `csv` - Comma-separated values

MD;
    }
}

// Example API Specification
function getExampleAPI(): APISpec
{
    $json = json_encode([
        'name' => 'BlogAPI',
        'baseUrl' => 'https://api.blog.com/v1',
        'routes' => [
            [
                'path' => '/users',
                'method' => 'GET',
                'parameters' => [
                    ['name' => 'limit', 'type' => 'int', 'required' => false, 'description' => 'Number of users to return'],
                    ['name' => 'offset', 'type' => 'int', 'required' => false, 'description' => 'Pagination offset']
                ],
                'description' => 'List all users',
                'auth' => 'bearer'
            ],
            [
                'path' => '/users',
                'method' => 'POST',
                'parameters' => [
                    ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'User name'],
                    ['name' => 'email', 'type' => 'string', 'required' => true, 'description' => 'User email']
                ],
                'description' => 'Create new user',
                'auth' => 'bearer'
            ],
            [
                'path' => '/users/{id}',
                'method' => 'GET',
                'parameters' => [
                    ['name' => 'id', 'type' => 'int', 'required' => true, 'description' => 'User ID']
                ],
                'description' => 'Get user by ID',
                'auth' => 'bearer'
            ],
            [
                'path' => '/posts',
                'method' => 'GET',
                'parameters' => [
                    ['name' => 'author', 'type' => 'int', 'required' => false, 'description' => 'Filter by author ID'],
                    ['name' => 'limit', 'type' => 'int', 'required' => false, 'description' => 'Number of posts']
                ],
                'description' => 'List posts'
            ],
            [
                'path' => '/posts',
                'method' => 'POST',
                'parameters' => [
                    ['name' => 'title', 'type' => 'string', 'required' => true, 'description' => 'Post title'],
                    ['name' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Post content'],
                    ['name' => 'author_id', 'type' => 'int', 'required' => true, 'description' => 'Author ID']
                ],
                'description' => 'Create new post',
                'auth' => 'bearer'
            ]
        ],
        'auth' => [
            'type' => 'bearer',
            'header' => 'Authorization'
        ]
    ]);

    return APISpec::fromJson($json);
}

// Main CLI Generator Application
function main(): void
{
    global $argv, $argc;

    if ($argc < 2) {
        echo "Universal CLI API Generator (PHP)\n";
        echo "Usage:\n";
        echo "  php cli-generator.php --example                 # Generate example CLI\n";
        echo "  php cli-generator.php --input <api-spec.json>   # Generate from JSON spec\n";
        echo "\n";
        echo "API Specification Format:\n";
        echo substr(json_encode(getExampleAPI(), JSON_PRETTY_PRINT), 0, 500) . "...\n";
        return;
    }

    switch ($argv[1]) {
        case '--example':
            echo "Generating example CLI from BlogAPI specification...\n";
            $spec = getExampleAPI();
            $generator = new CLIGenerator($spec);
            $generator->generateProject();
            break;

        case '--input':
            if ($argc < 3) {
                echo "Error: --input requires a filename\n";
                exit(1);
            }
            
            $filename = $argv[2];
            echo "Reading API specification from: {$filename}\n";
            
            if (!file_exists($filename)) {
                echo "Error: File not found: {$filename}\n";
                exit(1);
            }
            
            try {
                $content = file_get_contents($filename);
                $spec = APISpec::fromJson($content);
                $generator = new CLIGenerator($spec);
                $generator->generateProject();
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
                exit(1);
            }
            break;

        default:
            echo "Unknown option: {$argv[1]}\n";
            exit(1);
    }
}

// Run the generator if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    main();
}

?>