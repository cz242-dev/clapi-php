Key Features:
1. Complete PHP Implementation:

Object-oriented design with proper classes for API specs, routes, and parameters
JSON parsing for API specifications
Built-in HTTP client with authentication support
No external dependencies required

2. CLI Generation Engine:

Converts API routes to PHP CLI commands (users:get, posts:create)
Generates executable PHP scripts with proper shebang
Supports multiple output formats (JSON, table, CSV)
Built-in argument parsing and validation

3. Authentication Support:

Bearer token authentication via API_TOKEN environment variable
API key authentication via API_KEY environment variable
Automatic header injection for authenticated requests

4. Generated CLI Features:

Self-contained executable PHP files
Built-in help system and usage instructions
Path parameter replacement (/users/{id} → /users/123)
Multiple output formats with formatting functions
Error handling and proper exit codes

Usage Examples:
bash# Generate example CLI
php clapi.php --example

# Generate from your API spec
php clapi.php --input myapi.json

# Use the generated CLI
./blogapi-cli users:get --limit 10 --format table
./blogapi-cli posts:create --title "Hello" --body "World" --author-id 123
./blogapi-cli users:get --id 123 --format csv
Generated Project Structure:

Executable CLI file (blogapi-cli) - Main CLI application
composer.json - PHP project configuration
README.md - Usage documentation and examples

Key Advantages of PHP Version:

Zero dependencies - Uses only PHP built-in functions
Cross-platform - Runs on any system with PHP
Simple deployment - Single executable file
Framework integration - Easy to integrate with Laravel/Symfony route exports
Fast execution - No compilation needed

The generated CLI applications are fully functional, self-contained PHP executables that can be distributed and used independently. Each includes comprehensive help documentation and supports modern CLI patterns with proper error handling.
Would you like me to add specific framework integrations (Laravel Artisan route parser, Symfony routing component integration) or additional output language targets?



# BlogAPI CLI

Auto-generated CLI client for BlogAPI API.

## Installation

Make the CLI executable:
```bash
chmod +x blogapi-cli
```

## Usage

```bash
./blogapi-cli <command> [options]
```

## Available Commands

- `users:get` - List all users
- `users:create` - Create new user
- `users:id:get` - Get user by ID
- `posts:get` - List posts
- `posts:create` - Create new post


## Authentication

Set the required environment variable:
- `API_TOKEN` - For Bearer token authentication
- `API_KEY` - For API key authentication

## Examples

```bash
# List users
./blogapi-cli users:get --limit 10 --format table

# Create a new user
./blogapi-cli users:create --name "John Doe" --email "john@example.com"

# Get user by ID
./blogapi-cli users:get --id 123
```

## Output Formats

- `json` (default) - Pretty-printed JSON
- `table` - Tabular format
- `csv` - Comma-separated values
