# BiDirectionalSync-API-SQL

BiDirectionalSync-API-SQL is a PHP script designed to synchronize data between a SQL database and a RESTful API. The unique feature of this project is that neither database takes precedence over the other; the most recent change always wins.

## Features

- **Bidirectional Synchronization**: Data is synchronized both from the API to the SQL database and from the SQL database to the API.
- **Automatic Token Update**: Automatic management of the API token for authentication.
- **Last Modification Wins**: The latest modification between the API and SQL prevails, keeping the data up-to-date.
- **Detailed Logging**: Logs discrepancies and changes made during synchronization.

## Project Structure

- `get_tkn.php`: Script to retrieve and update the API token.
- `index.php`: Main script that performs data synchronization between SQL and API.
- `functions.php`: Contains all functions used for synchronization, logging, and data management.
- `config.php`: Configuration of tables and queries used in the script.
- `.env`: Environment variables configuration file.

## Getting Started

### Prerequisites

- PHP 7.x or later
- Composer
- SQL Server
- RESTful API endpoint

### Installation

1. **Clone the repository**:
    ```sh
    git clone https://github.com/yourusername/BiDirectionalSync-API-SQL.git
    cd BiDirectionalSync-API-SQL
    ```

2. **Install dependencies**:
    ```sh
    composer install
    ```

3. **Setup environment variables**:
    Rename `.env.example` to `.env` and configure your database and API settings.
    ```env
    NOME_SERVER=your_sql_server
    UID=your_sql_user
    PWD=your_sql_password
    API_TOKEN=your_initial_api_token
    API_URL=your_api_url
    ASSET_API_URL=your_asset_api_url
    DB_TKN=your_token_database
    DB_TEMP=your_temp_database
    BLOCCA_ESECUZIONE=true
    ```

### Usage

1. **Run the synchronization script**:
    ```sh
    php index.php
    ```

### Configuration

The `config.php` file contains the configuration for each table that needs to be synchronized. Here is an example configuration for a table:

```php
$tablecodesConfig = [
    'UM' => [
        'query' => "SELECT 'UM' AS tablecode, [Code] AS code, [Description] AS description, [LV Code] AS attrc02 FROM [dbo].[Pelletterie Palladio\$Unit of Measure]",
        'table_name' => 'Unit of Measure',
        'fields' => 'attrc02',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$Unit of Measure',
        'field_mapping' => [
            'attrc02' => 'LV Code'
        ],
        'code_mapping' => 'Code'
    ],
    // Add more table configurations here
];
```

### Logging

    Discrepancy Log: discrepancy_log.json logs all discrepancies found during synchronization.
    Previous Log: last_discrepancy_log.json stores the log from the previous run to help identify changes.

### Contributing

We welcome contributions to BiDirectionalSync-API-SQL! Here’s how you can help:

1. *Fork the repository* on GitHub.

2. *Clone your fork* locally:

```sh

git clone https://github.com/yourusername/BiDirectionalSync-API-SQL.git
cd BiDirectionalSync-API-SQL
```

3. *Create a new branch* for your feature or bugfix:

```sh

git checkout -b feature-or-bugfix-name
```

4. *Make your changes* and add tests if applicable.

5. *Commit your changes* with a meaningful commit message:

```sh

git commit -m "Description of the feature or fix"
```

6. *Push your branch* to your forked repository:

```sh

    git push origin feature-or-bugfix-name
```

7. *Open a Pull Request* on the original repository. Provide a detailed description of your changes and any relevant information for the maintainers to review.

### License

This project is licensed under the MIT [License](https://github.com/TobiaRigon/BiDirectionalSync-API-SQL/blob/main/LICENSE) - see the LICENSE file for details.