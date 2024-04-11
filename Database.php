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


    function buildQuery(string $query, array $args = []): string
    {
        if (empty($args)) {
            return $query;
        }

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

        $result = '';
        $paramIndex = 0;
        $length = strlen($query);

        for ($i = 0; $i < $length; $i++) {

            if ($query[$i] === '?' && isset($query[$i + 1])) {
                switch ($query[$i + 1]) {
                    case 'd':
                        $result .= intval($args[$paramIndex++]);
                        break;
                    case 'f':
                        $result .= floatval($args[$paramIndex++]);
                        break;
                    case 'a':
                        if (is_array($args[$paramIndex])) {
                            $array = $args[$paramIndex];
                            if (array_values($array) === $array) {
                                $result .= implode(', ', array_map(function($value) {
                                    return addslashes($value);
                                }, $array));
                            } else {
                                $result .= implode(', ', array_map(function($key, $value) {
                                        if ($value) {
                                            return "`$key` = '".addslashes($value)."'";
                                        } else
                                            return "`$key` = " . 'NULL';

                                }, array_keys($array), $array));
                            }
                        } else {
                            $result .= "'".addslashes($args[$paramIndex])."'";
                        }
                        $paramIndex++;
                        break;
                    case '#':
                        if (is_array($args[$paramIndex])) {
                            $result .= implode(', ', array_map(function($value) {
                                return "`$value`";
                            }, $args[$paramIndex]));
                        } else {
                            $result .= "`".$args[$paramIndex]."`";
                        }
                        $paramIndex++;
                        break;
                    default:
                        $result .= "'".addslashes($args[$paramIndex++])."' ";
                        break;
                }
                $i++;
            } else {
                $result .= $query[$i];
            }
        }

        return $result;
    }

    public function skip(): string
    {
        return '~';
    }

}