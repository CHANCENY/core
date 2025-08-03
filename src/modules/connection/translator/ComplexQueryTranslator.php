<?php

namespace Simp\Core\modules\connection\translator;

/**
 * A class that translates SQL-like queries into structured array formats
 * representable in NoSQL-style operations.
 */
class ComplexQueryTranslator
{
    /**
     * Translates a given SQL query string into a structured array representation based on the query type.
     *
     * @param string $query The SQL query string to be translated.
     * @return array An array containing the structured representation of the query or an error message if the query type is unsupported.
     */
    public static function translate(string $query): array
    {
        $query = trim($query);
        $queryType = strtoupper(strtok($query, " "));

        return match ($queryType) {
            'CREATE' => self::translateCreate($query),
            'INSERT' => self::translateInsert($query),
            'SELECT' => self::translateSelect($query),
            'UPDATE' => self::translateUpdate($query),
            'DELETE' => self::translateDelete($query),
            default => ['error' => 'Unsupported query type.'],
        };
    }

    /**
     * Translates an SQL "CREATE TABLE" query into a structured array representation.
     *
     * @param string $query The SQL query string to be parsed, expected to contain a "CREATE TABLE" statement.
     * @return array An associative array containing details of the operation ('createCollection'),
     *               the name of the table (keyed as 'collection'), and the fields of the table
     *               ('fields' array with field name and type).
     */
    private static function translateCreate(string $query): array
    {
        preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)` \((.+)\)/i', $query, $matches);
        $table = $matches[1] ?? null;
        $columnsDef = $matches[2] ?? '';
        $columns = [];

        foreach (explode(',', $columnsDef) as $def) {
            preg_match('/`(\w+)`\s+(\w+)(\(\d+\))?.*/', trim($def), $col);
            if (!empty($col)) {
                $columns[] = [
                    'name' => $col[1],
                    'type' => strtoupper($col[2])
                ];
            }
        }

        return [
            'operation' => 'createCollection',
            'collection' => $table,
            'fields' => $columns,
        ];
    }

    /**
     * Translates an SQL "INSERT INTO" query into a structured array representation.
     *
     * @param string $query The SQL query string to be parsed, expected to contain an "INSERT INTO" statement.
     * @return array An associative array containing details of the operation ('insertOne'),
     *               the name of the collection (keyed as 'collection'), and the data document
     *               ('document' array with field-to-value mapping from the query).
     */
    private static function translateInsert(string $query): array
    {
        preg_match('/INSERT INTO `(\w+)` \((.+)\) VALUES \((.+)\)/i', $query, $matches);
        $collection = $matches[1] ?? null;
        $fields = array_map('trim', explode(',', $matches[2] ?? ''));
        $values = array_map('trim', explode(',', $matches[3] ?? ''));
        $doc = [];
        foreach ($fields as $i => $field) {
            $doc[trim($field, '`')] = trim($values[$i], " '");
        }

        return [
            'operation' => 'insertOne',
            'collection' => $collection,
            'document' => $doc,
        ];
    }

    /**
     * Translates an SQL "SELECT" query into a structured array representation.
     *
     * @param string $query The SQL query string to be parsed, expected to contain a "SELECT" statement.
     * @return array An associative array containing the intended operation ('find'),
     *               the name of the collection ('collection'), the fields to project ('projection'),
     *               and any filters ('filter') derived from the query's WHERE clause.
     */
    private static function translateSelect(string $query): array
    {
        preg_match('/SELECT (.+) FROM `(\w+)`(?: WHERE (.+))?/i', $query, $matches);
        $fields = $matches[1] === '*' ? [] : array_map('trim', explode(',', $matches[1]));
        $collection = $matches[2] ?? null;
        $filter = [];

        if (!empty($matches[3])) {
            $filter = self::parseWhereClause($matches[3]);
        }

        return [
            'operation' => 'find',
            'collection' => $collection,
            'filter' => $filter,
            'projection' => $fields,
        ];
    }

    /**
     * Parses a SQL UPDATE query and translates it into a structured associative array
     * containing the update operation details for further processing.
     *
     * @param string $query The SQL UPDATE query string to be translated.
     *
     * @return array An associative array containing:
     *               - 'operation': The type of operation (e.g., 'updateOne').
     *               - 'collection': The name of the target collection from the query.
     *               - 'filter': The parsed filter or WHERE clause conditions.
     *               - 'update': An array specifying the fields and their updated values.
     */
    private static function translateUpdate(string $query): array
    {
        preg_match('/UPDATE `(\w+)` SET (.+?) WHERE (.+)/i', $query, $matches);
        $collection = $matches[1] ?? null;
        $set = $matches[2] ?? '';
        $where = $matches[3] ?? '';

        $update = [];
        foreach (explode(',', $set) as $part) {
            [$field, $value] = array_map('trim', explode('=', $part));
            $update[trim($field, '`')] = trim($value, " '");
        }

        $filter = self::parseWhereClause($where);

        return [
            'operation' => 'updateOne',
            'collection' => $collection,
            'filter' => $filter,
            'update' => ['$set' => $update],
        ];
    }

    /**
     * Parses a SQL DELETE query and translates it into a structured associative array
     * containing the delete operation details for further processing.
     *
     * @param string $query The SQL DELETE query string to be translated.
     *
     * @return array An associative array containing:
     *               - 'operation': The type of operation (e.g., 'deleteMany').
     *               - 'collection': The name of the target collection from the query.
     *               - 'filter': The parsed filter or WHERE clause conditions, if any.
     */
    private static function translateDelete(string $query): array
    {
        preg_match('/DELETE FROM `(\w+)`(?: WHERE (.+))?/i', $query, $matches);
        $collection = $matches[1] ?? null;
        $filter = !empty($matches[2]) ? self::parseWhereClause($matches[2]) : [];

        return [
            'operation' => 'deleteMany',
            'collection' => $collection,
            'filter' => $filter,
        ];
    }

    /**
     * Parses a SQL WHERE clause and translates it into a structured associative array
     * representing the filtering criteria for further processing.
     *
     * @param string $clause The SQL WHERE clause string to be parsed.
     *
     * @return array An associative array containing the parsed filter conditions, structured with
     *               logical operators (e.g., '$and', '$or') and comparison operators (e.g., '$ne', '$gt').
     */
    private static function parseWhereClause(string $clause): array
    {
        $clause = trim($clause);
        $filters = [];

        // Split by AND/OR, basic only for now
        $parts = preg_split('/\s+(AND|OR)\s+/i', $clause, -1, PREG_SPLIT_DELIM_CAPTURE);
        $currentLogic = null;

        foreach ($parts as $part) {
            $part = trim($part);
            if (strtoupper($part) === 'AND' || strtoupper($part) === 'OR') {
                $currentLogic = strtoupper($part);
                continue;
            }

            preg_match('/`?(\w+)`?\s*(=|!=|<|>|<=|>=|LIKE)\s*([^"]\S*|"[^"]+")/i', $part, $match);
            if (count($match) >= 4) {
                [$_, $field, $op, $value] = $match;
                $value = trim($value, " '");

                $condition = match ($op) {
                    '=' => [$field => $value],
                    '!=' => [$field => ['$ne' => $value]],
                    '>' => [$field => ['$gt' => $value]],
                    '<' => [$field => ['$lt' => $value]],
                    '>=' => [$field => ['$gte' => $value]],
                    '<=' => [$field => ['$lte' => $value]],
                    'LIKE' => [$field => ['$regex' => str_replace('%', '.*', $value)]],
                    default => [],
                };

                if ($currentLogic === 'OR') {
                    if (!isset($filters['$or'])) {
                        $filters['$or'] = [];
                    }
                    $filters['$or'][] = $condition;
                } elseif ($currentLogic === 'AND') {
                    $filters = array_merge($filters, $condition);
                } else {
                    $filters = array_merge($filters, $condition);
                }
            }
        }

        return $filters;
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
