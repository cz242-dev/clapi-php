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
