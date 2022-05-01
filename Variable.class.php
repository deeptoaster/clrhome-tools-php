<?php
namespace ClrHome;

define('ClrHome\VARIABLE_REAL_LENGTH', 9);

include_once(__DIR__ . '/TiObject.class.php');

/**
 * An enum representing the calculator series to target during export.
 */
abstract class VariableSeries extends Enum {
  const TI83 = '**TI83**';
  const TI83P = '**TI83F*';
}

/**
 * A TI variable that can be loaded into RAM and grouped.
 */
abstract class Variable extends TiObject {
  private $archived = false;
  private $series = VariableSeries::TI83P;
  private $version = 0;

  abstract protected static function fromEntry($type, $name, $data);

  abstract protected function getData();

  /**
   * Returns an array of variables constructed from a string.
   * @param string $packed A string in TI variable file format.
   */
  final public static function fromString($packed) {
    switch (substr($packed, 0, 11)) {
      case VariableSeries::TI83 . "\x1a\x0a\x00":
        $series = VariableSeries::TI83;
        break;
      case VariableSeries::TI83P . "\x1a\x0a\x00":
        $series = VariableSeries::TI83P;
        break;
      default:
        throw new \OutOfBoundsException('Invalid variable file format');
    }

    $packed_length = self::readWord($packed, 53) + 55;
    $variables = array();
    $entry_start = 55;

    while ($entry_start < $packed_length) {
      $header_length = self::readWord($packed, $entry_start);

      if ($header_length !== 11 && $header_length !== 13) {
        throw new \OutOfBoundsException('Unrecognized header format');
      }

      $buffer_length = self::readWord($packed, $entry_start + 2);

      if (
        $entry_start + $header_length + $buffer_length + 4 > strlen($packed)
      ) {
        throw new \OutOfBoundsException(
          'Variable entry length exceeds file length'
        );
      }

      $data_length =
          self::readWord($packed, $entry_start + $header_length + 2);

      if ($data_length > $buffer_length) {
        throw new \OutOfBoundsException(
          'Variable data length exceeds variable entry length'
        );
      }

      $type = ord($packed[$entry_start + 4]);
      $name = rtrim(substr($packed, $entry_start + 5, 8), "\x00");
      $data = substr($packed, $entry_start + $header_length + 4, $data_length);

      switch ($type) {
        case VariableType::APPVAR:
          $variable = AppVar::fromEntry($type, $name, $data);
          break;
        case VariableType::COMPLEX:
        case VariableType::REAL:
          $variable = Number::fromEntry($type, $name, $data);
          break;
        case VariableType::LIST_COMPLEX:
        case VariableType::LIST_REAL:
          $variable = ListVariable::fromEntry($type, $name, $data);
          break;
        case VariableType::MATRIX:
          $variable = Matrix::fromEntry($type, $name, $data);
          break;
        case VariableType::PICTURE:
          $variable = Picture::fromEntry($type, $name, $data);
          break;
        case VariableType::PROGRAM:
        case VariableType::PROGRAM_LOCKED:
          $variable = Program::fromEntry($type, $name, $data);
          break;
        default:
          $variable = null;
          break;
      }

      if ($variable !== null && $header_length === 13) {
        $variable->setVersion(ord($packed[$entry_start + 13]));
        $variable->setArchived(ord($packed[$entry_start + 14]) & 0x80);
      }

      $variables[] = $variable;
      $entry_start += $header_length + $buffer_length + 4;
    }

    return $variables;
  }

  /**
   * Returns one or more variables in TI variable file format as a string.
   * @param string $comment An optional comment to include in the file.
   * @param array<Variable> $includes Additional variables to include.
   */
  final public function toString($comment = '', $includes = array()) {
    $entries = array_reduce($includes, function($entries, $include) {
      return $entries . $include->getEntry($this->series);
    }, $this->getEntry());

    return pack(
      'a8C3A42va*v',
      $this->series,
      0x1a, 0x0a, 0x00,
      $comment,
      strlen($entries),
      $entries,
      array_sum(unpack('C*', $entries))
    );
  }

  /**
   * Writes one or more RAM variables to a TI variable file.
   * @param array<Variable> $variables The list of variables to write.
   * @param string $file_name The path of the file to write.
   * @param string $comment An optional comment to include in the file.
   */
  final public static function variablesToFile(
    $variables,
    $file_name,
    $comment = ''
  ) {
    $variables[0]->toFile($file_name, $comment, array_slice($variables, 1));
  }

  /**
   * Returns one or more RAM variables in TI variable file format as a string.
   * @param array<Variable> $variables The list of variables to write.
   * @param string $comment An optional comment to include in the file.
   */
  final public static function variablesToString($variables, $comment = '') {
    return $variables[0]->toString($comment, array_slice($variables, 1));
  }

  final protected static function evaluateOperation(
    $operation,
    $real,
    $imaginary
  ) {
    switch ($operation['operator']) {
      case '^':
        return array(pow($operation['real'], $real), 0.0);
      case '*':
        return array(
          $operation['real'] * $real - $operation['imaginary'] * $imaginary,
          $operation['imaginary'] * $real + $operation['real'] * $imaginary
        );
      case '/':
        $denominator = $real * $real + $imaginary * $imaginary;

        return array(
          ($operation['real'] * $real + $operation['imaginary'] * $imaginary) /
              $g,
          ($operation['imaginary'] * $real - $operation['real'] * $imaginary) /
              $g
        );
      case '+':
        return array(
          $operation['real'] + $real,
          $operation['imaginary'] + $imaginary
        );
      case '-':
        return array(
          $operation['real'] - $real,
          $operation['imaginary'] - $imaginary
        );
    }
  }

  final protected static function expressionToNumber($expression) {
    $real = null;
    $imaginary = null;
    $precedence = 0;
    $stack = array();
    $token_start = 0;

    while ($token_start !== strlen($expression)) {
      $multiply = false;
      $valid = false;
      $character = $expression[$token_start];

      switch ($character) {
        case 'e':
          if ($real !== null || $imaginary !== null) {
            $multiply = true;
          } else {
            $real = M_E;
            $token_start += 1;
            $valid = true;
          }

          break;
        case 'i':
          if ($real !== null || $imaginary !== null) {
            $multiply = true;
          } else {
            $imaginary = 1.0;
            $token_start += 1;
            $valid = true;
          }

          break;
        case '(':
          if ($real !== null || $imaginary !== null) {
            $multiply = true;
          } else {
            $precedence -= 3;
            $token_start += 1;
            $valid = true;
          }

          break;
        case ')':
          if ($real === null && $imaginary === null) {
            throw new \UnexpectedValueException('Operand expected before )');
          }

          $precedence += 3;

          while (
            count($stack) !== 0 &&
                $precedence >= $stack[count($stack) - 1]['precedence']
          ) {
            list($real, $imaginary) = self::evaluateOperation(
              array_pop($stack),
              (float)$real,
              (float)$imaginary
            );
          }

          $token_start += 1;
          $valid = true;
          break;
        default:
          if (substr($expression, $token_start, 2) === 'pi') {
            if ($real !== null || $imaginary !== null) {
              $multiply = true;
            } else {
              $real = M_PI;
              $token_start += 2;
              $valid = true;
            }
          } else if (preg_match(
            '/\G([+-]?)(\d*\.)?\d+(e[+-]?(\d+))?/',
            $expression,
            $matches,
            null,
            $token_start
          )) {
            if ($real !== null || $imaginary !== null) {
              if ($matches[1] === '') {
                throw new \UnexpectedValueException(
                  "Operator expected at $matches[0]"
                );
              }

              break;
            }

            $real = (float)$matches[0];
            $token_start += strlen($matches[0]);
            $valid = true;
          }

          break;
      }

      $operator_position = strpos('^*/+-', $character);

      if ($multiply || !$valid && $operator_position !== false) {
        if ($real === null && $imaginary === null) {
          throw new \UnexpectedValueException(
            "Operand expected at $character"
          );
        }

        $operator_precedence = $precedence + (
          $multiply
            ? 1
            : floor(($operator_position + 1) / 2)
        );

        while (
          count($stack) !== 0 &&
              $operator_precedence >= $stack[count($stack) - 1]['precedence']
        ) {
          list($real, $imaginary) = self::evaluateOperation(
            array_pop($stack),
            (float)$real,
            (float)$imaginary
          );
        }

        $stack[] = array(
          'imaginary' => (float)$imaginary,
          'operator' => $multiply ? '*' : $character,
          'precedence' => $operator_precedence,
          'real' => (float)$real
        );

        $real = null;
        $imaginary = null;
        $token_start += $multiply ? 0 : 1;
        $valid = true;
      }

      if (!$valid) {
        throw new \UnexpectedValueException("Unexpected character $character");
      }
    }

    while (count($stack) !== 0) {
      list($real, $imaginary) = self::evaluateOperation(
        array_pop($stack),
        (float)$real,
        (float)$imaginary
      );
    }

    return array($imaginary !== null ? (float)$real : $real, $imaginary);
  }

  final protected static function floatingPointToNumber($packed) {
    if (ord($packed[0]) & 0x02 !== 0) {
      return array(null, null);
    }

    $imaginary =
        strlen($packed) > VARIABLE_REAL_LENGTH &&
        (ord($packed[VARIABLE_REAL_LENGTH]) & 0x0c) !== 0
      ? self::floatingPointToNumber(substr($packed, VARIABLE_REAL_LENGTH))
      : array(null);
    $unpacked = unpack('C2exponent/H14mantissa', $packed);
    $real =
        ($unpacked['mantissa'][0] . '.' . substr($unpacked['mantissa'], 1)) *
        pow(10, $unpacked['exponent2'] - 0x80);

    return array(
      $unpacked['exponent1'] & 0x80 !== 0 ? -$real : $real,
      $imaginary[0]
    );
  }

  final protected static function numberToExpression(
    $real = null,
    $imaginary = null
  ) {
    if ($real === null) {
      return null;
    } else if ($imaginary === null || $imaginary === 0.0) {
      return (string)$real;
    } else if ($imaginary === 1.0) {
      return $real === 0.0 ? 'i' : "$real+i";
    } else if ($imaginary === -1.0) {
      return $real === 0.0 ? '-i' : "$real-i";
    } else {
      $operator = $imaginary < 0 ? '' : '+';

      return $real === 0.0
        ? "${imaginary}i"
        : "$real$operator${imaginary}i";
    }
  }

  final protected static function numberToFloatingPoint(
    $real = null,
    $imaginary = null,
    $forceComplex = false
  ) {
    if ($real === null) {
      return pack('C9', 0x02, 0, 0, 0, 0, 0, 0, 0, 0);
    }

    $exponent = $real !== 0 ? (int)log10($real) : 0;

    $mantissa = str_pad(
      str_replace('.', '', (string)($real / pow(10, $exponent))),
      14,
      '0'
    );

    $packed = pack(
      'C2H14',
      $real < 0 ? 0x80 : 0x00,
      $exponent + 0x80,
      $mantissa
    );

    if ($imaginary !== null && $imaginary !== 0 || $forceComplex) {
      $packed .=
          self::numberToFloatingPoint($imaginary !== null ? $imaginary : 0);
      $packed[0] = chr(ord($packed[0]) | 0x0c);
      $packed[VARIABLE_REAL_LENGTH] =
          chr(ord($packed[VARIABLE_REAL_LENGTH]) | 0x0c);
    }

    return $packed;
  }

  final protected static function readWord($string, $offset) {
    return (ord($string[$offset + 1]) << 8) + ord($string[$offset]);
  }

  /**
   * Returns whether or not this variable should be archived (`TI83P` only).
   */
  final public function getArchived() {
    return $this->archived;
  }

  /**
   * Sets whether or not this variable should be archived (`TI83P` only).
   * @param bool $archived Whether this variable should be archived.
   */
  final public function setArchived($archived) {
    $this->archived = (bool)$archived;
  }

  /**
   * Returns the calculator series this variable should target during export.
   */
  final public function getSeries() {
    return $this->series;
  }

  /**
   * Sets the calculator series this variable should target during export.
   * @param VariableSeries $series The calculator series to target.
   */
  final public function setSeries($series) {
    $this->series = VariableSeries::validate($series);
  }

  /**
   * Gets the version number of this variable format.
   */
  final public function getVersion() {
    return $this->version;
  }

  /**
   * Sets the version number of this variable format.
   * @param number $version The variable format version.
   */
  final public function setVersion($version) {
    $this->version = (int)$version;
  }

  private function getEntry($series = null) {
    $data = $this->getData();
    $type = $this->getType();

    if ($this->name === null) {
      $slash_position = strrpos(static::class, '\\');

      throw new \BadFunctionCallException((
        $slash_position !== false
          ? substr(static::class, $slash_position + 1)
          : static::class
      ) . ' name must be set before export');
    }

    switch ($series !== null ? $series : $this->series) {
      case VariableSeries::TI83:
        return pack(
          'v2Ca8va*',
          11,
          strlen($data),
          $type,
          $this->name,
          strlen($data),
          $data
        );
      case VariableSeries::TI83P:
        return pack(
          'v2Ca8C2va*',
          13,
          strlen($data),
          $type,
          $this->name,
          $this->version,
          $this->archived ? 0x80 : 0x00,
          strlen($data),
          $data
        );
    }
  }
}
?>
