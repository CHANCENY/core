<?php

namespace Simp\Core\modules\connection\translator;

use Exception;
use Simp\Core\modules\mongodb\ClientCredentials;

/**
 * QueryTranslator class provides methods to translate various SQL commands
 * into an equivalent representation suitable for use in a different environment,
 * such as a database abstraction layer or a NoSQL database.
 */

class QueryTranslator
{
    protected string $query;

    /**
     * Constructor method to initialize the class with a query string.
     *
     * @param string $query The query string to be processed and stored.
     * @return void
     */
    public function __construct(string $query)
    {
        $this->query = trim($query);
    }

    /**
     * Translates an SQL query string into its respective structured representation.
     *
     * This method checks the type of SQL command in the query and delegates
     * the translation to specific methods dedicated to handling each type of command.
     *
     * @return array The structured representation of the translated SQL query.
     * @throws Exception If the SQL command is unsupported.
     */
    public function translate(): array
    {
        $query = strtoupper($this->query);

        if (str_starts_with($query, 'CREATE TABLE')) {
            return $this->translateCreateTable();
        }

        if (str_starts_with($query, 'SELECT')) {
            return $this->translateSelect();
        }

        if (str_starts_with($query, 'INSERT INTO')) {
            return $this->translateInsert();
        }

        if (str_starts_with($query, 'UPDATE')) {
            return $this->translateUpdate();
        }

        if (str_starts_with($query, 'DELETE FROM')) {
            return $this->translateDelete();
        }

        throw new Exception("Unsupported SQL command.");
    }

    /**
     * Parses a SQL CREATE TABLE query and translates it into an array representation.
     *
     * The method extracts the table name, column definitions, and additional metadata
     * from the provided SQL query and returns it in a structured format. If the query
     * does not match the expected CREATE TABLE syntax, an exception is thrown.
     *
     * @return array An associative array containing the parsed table information, including:
     *               - 'command': The operation being performed (e.g., 'createCollection').
     *               - 'collection': The name of the table extracted from the query.
     *               - 'schema': An array of column definitions with details such as type and length.
     *
     * @throws Exception If the provided query does not conform to a valid CREATE TABLE syntax.
     */
    protected function translateCreateTable(): array
    {
        $pattern = '/CREATE TABLE IF NOT EXISTS `?(\w+)`?\s*\((.*?)\)/is';
        if (!preg_match($pattern, $this->query, $matches)) {
            throw new Exception("Invalid CREATE TABLE syntax.");
        }

        $table = $matches[1];
        $columnsRaw = $matches[2];
        $columns = [];

        foreach (explode(',', $columnsRaw) as $colLine) {
            if (preg_match('/`?(\w+)`?\s+(\w+)(\((\d+)\))?/i', trim($colLine), $colMatch)) {
                $columns[$colMatch[1]] = [
                    'type' => strtoupper($colMatch[2]),
                    'length' => $colMatch[4] ?? null,
                ];
            }
        }

        return [
            'command' => 'createCollection',
            'collection' => $table,
            'schema' => $columns,
        ];
    }

    /**
     * Translates a SQL SELECT query into an associative array representation.
     *
     * This method parses a SELECT statement, extracts the fields, the target collection (table),
     * and optional WHERE clause conditions, and transforms them into a structured array.
     *
     * @return array An associative array containing:
     *               - `command`: The operation to perform, e.g., 'find'.
     *               - `collection`: The name of the collection or table targeted by the query.
     *               - `filter`: An array representing the parsed filter conditions from the WHERE clause.
     *               - `projection`: An array of fields to be included in the result, or an empty array for all fields.
     *
     * @throws Exception If the SELECT query syntax is invalid.
     */
    protected function translateSelect(): array
    {
        $pattern = '/SELECT\s+(.*?)\s+FROM\s+`?(\w+)`?(?:\s+WHERE\s+(.*))?/is';
        if (!preg_match($pattern, $this->query, $matches)) {
            throw new Exception("Invalid SELECT syntax.");
        }

        $fields = $matches[1] === '*' ? [] : array_map('trim', explode(',', $matches[1]));
        $collection = $matches[2];
        $filter = [];

        if (!empty($matches[3])) {
            $filter = $this->parseWhereClause($matches[3]);
        }

        return [
            'command' => 'find',
            'collection' => $collection,
            'filter' => $filter,
            'projection' => $fields,
            'database' => ClientCredentials::credentials()->getCredentials()['database'] ?? '',
        ];
    }

    /**
     * Parses an SQL INSERT INTO query and translates it into a structured array
     * representing an insert operation in a different data context.
     *
     * @return array An associative array containing:
     *               - 'command': The operation command (e.g., 'insertOne').
     *               - 'collection': The target collection name extracted from the query.
     *               - 'document': The document data mapped from fields and values in the query.
     * @throws Exception If the query does not match the expected INSERT INTO syntax.
     */
    protected function translateInsert(): array
    {
        $pattern = '/INSERT INTO `?(\w+)`? \((.*?)\) VALUES \((.*?)\)/is';
        if (!preg_match($pattern, $this->query, $matches)) {
            throw new Exception("Invalid INSERT INTO syntax.");
        }

        $collection = $matches[1];
        $fields = array_map('trim', explode(',', $matches[2]));
        $values = array_map('trim', explode(',', $matches[3]));
        $document = array_combine($fields, $values);

        return [
            'command' => 'insertOne',
            'collection' => $collection,
            'document' => $this->cleanValues($document),
            'database' => ClientCredentials::credentials()->getCredentials()['database'] ?? '',
        ];
    }

    /**
     * Parses an SQL UPDATE statement and translates it into a structured array.
     *
     * The method uses regular expressions to extract the collection name,
     * update fields, and filter conditions from the SQL query. It then
     * processes and formats the extracted data to return a standardized
     * representation for further use.
     *
     * @return array An associative array containing the translated update operation,
     *               including the fields to modify, the targeted collection, and
     *               the filtering conditions.
     * @throws Exception If the provided SQL query does not match the expected UPDATE syntax.
     */
    protected function translateUpdate(): array
    {
        $pattern = '/UPDATE `?(\w+)`? SET (.*?) WHERE (.*)/is';
        if (!preg_match($pattern, $this->query, $matches)) {
            throw new Exception("Invalid UPDATE syntax.");
        }

        $collection = $matches[1];
        $updatesRaw = explode(',', $matches[2]);
        $filter = $this->parseWhereClause($matches[3]);

        $update = [];
        foreach ($updatesRaw as $pair) {
            [$key, $value] = array_map('trim', explode('=', $pair));
            $update[$key] = $this->cleanValue($value);
        }

        return [
            'command' => 'updateOne',
            'collection' => $collection,
            'filter' => $filter,
            'update' => ['$set' => $update],
            'database' => ClientCredentials::credentials()->getCredentials()['database'] ?? '',
        ];
    }

    /**
     * Translates a DELETE SQL query into a structured array format suitable for further processing.
     *
     * @return array An associative array containing:
     *               - 'command': The operation type, set to 'deleteMany'.
     *               - 'collection': The name of the collection or table being targeted.
     *               - 'filter': The parsed conditions for deletion as an array, or an empty array if no conditions are specified.
     *
     * @throws Exception If the query does not follow valid DELETE syntax.
     */
    protected function translateDelete(): array
    {
        $pattern = '/DELETE FROM `?(\w+)`?(?:\s+WHERE\s+(.*))?/is';
        if (!preg_match($pattern, $this->query, $matches)) {
            throw new Exception("Invalid DELETE syntax.");
        }

        $collection = $matches[1];
        $filter = isset($matches[2]) ? $this->parseWhereClause($matches[2]) : [];

        return [
            'command' => 'deleteMany',
            'collection' => $collection,
            'filter' => $filter,
            'database' => ClientCredentials::credentials()->getCredentials()['database'] ?? '',
        ];
    }

    /**
     * Parses a WHERE clause and extracts conditions into an associative array.
     *
     * @param string $clause The WHERE clause string to be parsed.
     * @return array An associative array of column-value pairs extracted from the clause.
     */
    protected function parseWhereClause(string $clause): array
    {
        $filters = [];
        foreach (preg_split('/\s+AND\s+/i', $clause) as $condition) {
            if (preg_match('/`?(\w+)`?\s*=\s*([\'\"]?)(.*?)\2/i', trim($condition), $condMatch)) {
                $filters[$condMatch[1]] = $this->cleanValue($condMatch[3]);
            }
        }
        return $filters;
    }

    /**
     * Iterates through an array and applies a cleaning process to each element.
     *
     * @param array $values The array of values to be cleaned.
     * @return array The array with each element cleaned.
     */
    protected function cleanValues(array $values): array
    {
        foreach ($values as $k => $v) {
            $values[$k] = $this->cleanValue($v);
        }
        return $values;
    }

    /**
     * Cleans and processes a given string value by trimming, type casting, and interpreting specific cases.
     *
     * @param string $value The input string value to be processed.
     * @return mixed The processed value, which may be a float, integer, null, boolean, or a trimmed string.
     */
    protected function cleanValue(string $value): mixed
    {
        $value = trim($value, "'\"");
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        if (strtolower($value) === 'null') return null;
        if (strtolower($value) === 'true') return true;
        if (strtolower($value) === 'false') return false;
        return $value;
    }

    public function parseCreateTable(string $query): array
    {
        $query = trim($query);

        // Normalize spacing and remove backticks
        $query = preg_replace('/\s+/', ' ', str_replace('`', '', $query));

        // Match table name
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)\s*\((.+)\)/i', $query, $matches);
        if (!$matches || count($matches) < 3) {
            throw new \InvalidArgumentException("Invalid CREATE TABLE statement.");
        }

        $collection = $matches[1];
        $fieldsStr = $matches[2];

        // Split fields by comma, respecting parentheses in e.g. ENUM, DECIMAL
        $fields = $this->splitFields($fieldsStr);

        $resultFields = [];
        foreach ($fields as $field) {
            $field = trim($field);

            // Skip constraints
            if (stripos($field, 'PRIMARY KEY') === 0 ||
                stripos($field, 'UNIQUE') === 0 ||
                stripos($field, 'KEY') === 0 ||
                stripos($field, 'INDEX') === 0 ||
                stripos($field, 'CONSTRAINT') === 0
            ) {
                continue;
            }

            // Match field definition: name type [options...]
            preg_match('/^(\w+)\s+([A-Z]+(?:\(\d+\))?)(.*)$/i', $field, $fmatch);
            if (!$fmatch) continue;

            $name = $fmatch[1];
            $type = strtoupper($fmatch[2]);
            $options = strtoupper($fmatch[3]);

            $resultFields[$name] = [
                'type' => $type,
                'not_null' => str_contains($options, 'NOT NULL'),
                'auto_increment' => str_contains($options, 'AUTO_INCREMENT'),
                'default' => $this->extractDefault($options),
            ];
        }

        return [
            'collection' => $collection,
            'fields' => $resultFields
        ];
    }

    private function splitFields(string $fieldsStr): array
    {
        $fields = [];
        $buffer = '';
        $depth = 0;
        $chars = str_split($fieldsStr);

        foreach ($chars as $char) {
            if ($char === '(') $depth++;
            if ($char === ')') $depth--;
            if ($char === ',' && $depth === 0) {
                $fields[] = $buffer;
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        if (trim($buffer) !== '') {
            $fields[] = $buffer;
        }

        return $fields;
    }

    private function extractDefault(string $options): string|null|float
    {
        if (preg_match('/DEFAULT\s+([^\s]+)/i', $options, $m)) {
            $default = trim($m[1], "'\"");
            if (strcasecmp($default, 'NULL') === 0) return null;
            if (is_numeric($default)) return (float)$default;
            return $default;
        }
        return null;
    }

}
