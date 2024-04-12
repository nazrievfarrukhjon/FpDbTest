<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }


    /**
     * @throws Exception
     */
    function buildQuery(string $query, array $args = []): string
    {
        if (strpos($query, '?') === false) {
            return $query;
        }

        $query = $this->parseOptionalBlock($query, $args);

        $builtQuery = '';
        $paramIndex = 0;
        $length = strlen($query);

        for ($i = 0; $i < $length; $i++) {
            //
            if ($query[$i] === '?') {
                if (isset($query[$i + 1])) {
                    $specifier = $query[$i + 1];
                    if ($specifier !== '' && !preg_match('/[dfa# ]/', $specifier)) {
                        throw new Exception("Invalid placeholder specifier: '$specifier' after '?'");
                    } elseif ($specifier === 'd') {
                        $builtQuery .= intval($args[$paramIndex++]);
                    } elseif ($specifier === 'f') {
                        $builtQuery .= floatval($args[$paramIndex++]);
                    } elseif ($specifier === 'a') {
                        if (\is_array($args[$paramIndex])) {
                            $builtQuery = $this->implodeAssociativeArr($args[$paramIndex], $builtQuery);
                        } else {
                            $builtQuery .= "'" . $this->addslashes($args[$paramIndex]) . "'";
                        }
                        $paramIndex++;
                    } elseif ($specifier === '#') {
                        if (\is_array($args[$paramIndex])) {
                            $values = [];
                            foreach ($args[$paramIndex] as $value) {
                                $values[] = "`$value`";
                            }
                            $builtQuery .= implode(', ', $values);
                        } else {
                            $builtQuery .= "`" . $args[$paramIndex] . "`";
                        }
                        $paramIndex++;
                    } else {
                        $builtQuery .= "'" . $this->addSlashes($args[$paramIndex++]) . "' ";
                    }
                    $i++;
                } else {
                    $builtQuery .= $query[$i];
                }
            } else {
                $builtQuery .= $query[$i];
            }
        }

        return $builtQuery;
    }

    private function addSlashes($value): string
    {
        $chars = ["\\", "'", "\"", "\x00", "\x1a"];

        $escapedValue = '';
        for ($i = 0; $i < strlen($value); $i++) {
            if (in_array($value[$i], $chars)) {
                $escapedValue .= "\\" . $value[$i];
            } else {
                $escapedValue .= $value[$i];
            }
        }
        return $escapedValue;
    }

    public function skip(): string
    {
        return '~';
    }
    
    private function parseOptionalBlock(string $query, array $args): string
    {
        if (str_contains($query, '{')) {
            foreach ($args as $a) {
                if ($a === '~') {
                    $q1 = substr($query, 0, strpos($query, '{'));
                    $q2 = substr($query, strpos($query, '}') + 1, strlen($query) - strpos($query, '}'));
                    $query = $q1 . $q2;
                }
            }

            $query = str_replace(['{', '}'], '', $query);
        }
        
        return $query;
    }

    public function implodeAssociativeArr(array $args, string $builtQuery): string
    {
        $array = $args;
        if ($this->isSimpleIndexedArray($array)) {
            foreach ($array as $value) {
                $builtQuery .= "" . str_replace("'", "''", $value) . ", ";
            }
            $builtQuery = rtrim($builtQuery, ', ');
        } else {
            foreach ($array as $key => $value) {
                if ($value) {
                    $builtQuery .= "`$key` = '" . addslashes($value) . "', ";
                } else {
                    $builtQuery .= "`$key` = NULL, ";
                }
            }
            $builtQuery = rtrim($builtQuery, ', ');
        }
        return $builtQuery;
    }

    // Duck typing
    public function isSimpleIndexedArray(array $array): bool
    {
        return array_values($array) === $array;
    }

}