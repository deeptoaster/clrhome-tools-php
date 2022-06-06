<?php
namespace ClrHome;

define('ClrHome\FLOATING_POINT_REAL_LENGTH', 9);

include_once(__DIR__ . '/common.php');

/**
 * REpresents a complex number.
 */
class SimpleNumber extends Immutable {
  protected $imaginary;
  protected $real;

  public function __construct($real = null, $imaginary = null) {
    $this->imaginary = $imaginary !== null ? (float)$imaginary : null;
    $this->real = $real !== null ? (float)$real : null;
  }

  final public static function fromExpression($expression) {
    $number = new self();
    $precedence = 0;
    $stack = array();
    $token_start = 0;

    while ($token_start !== strlen($expression)) {
      $multiply = false;
      $valid = false;
      $character = $expression[$token_start];

      switch ($character) {
        case 'e':
          if ($number->real !== null || $number->imaginary !== null) {
            $multiply = true;
          } else {
            $number = new self(M_E);
            $token_start += 1;
            $valid = true;
          }

          break;
        case 'i':
          if ($number->real !== null || $number->imaginary !== null) {
            $multiply = true;
          } else {
            $number = new self(0, 1);
            $token_start += 1;
            $valid = true;
          }

          break;
        case '(':
          if ($number->real !== null || $number->imaginary !== null) {
            $multiply = true;
          } else {
            $precedence -= 3;
            $token_start += 1;
            $valid = true;
          }

          break;
        case ')':
          if ($number->real === null && $number->imaginary === null) {
            throw new \UnexpectedValueException('Operand expected before )');
          }

          $precedence += 3;

          while (
            count($stack) !== 0 &&
                $precedence >= $stack[count($stack) - 1]['precedence']
          ) {
            $operation = array_pop($stack);

            $number = $operation['number']->evaluateOperation(
              $operation['operator'],
              $number
            );
          }

          $token_start += 1;
          $valid = true;
          break;
        default:
          if (substr($expression, $token_start, 2) === 'pi') {
            if ($number->real !== null || $number->imaginary !== null) {
              $multiply = true;
            } else {
              $number = new self(M_PI);
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
            if ($number->real !== null || $number->imaginary !== null) {
              if ($matches[1] === '') {
                throw new \UnexpectedValueException(
                  "Operator expected at $matches[0]"
                );
              }

              break;
            }

            $number = new self($matches[0]);
            $token_start += strlen($matches[0]);
            $valid = true;
          }

          break;
      }

      $operator_position = strpos('^*/+-', $character);

      if ($multiply || !$valid && $operator_position !== false) {
        if ($number->real === null && $number->imaginary === null) {
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
          $operation = array_pop($stack);

          $number = $operation['number']->evaluateOperation(
            $operation['operator'],
            $number
          );
        }

        $stack[] = array(
          'number' => $number,
          'operator' => $multiply ? '*' : $character,
          'precedence' => $operator_precedence
        );

        $number = new self();
        $token_start += $multiply ? 0 : 1;
        $valid = true;
      }

      if (!$valid) {
        throw new \UnexpectedValueException("Unexpected character $character");
      }
    }

    while (count($stack) !== 0) {
      $operation = array_pop($stack);

      $number = $operation['number']->evaluateOperation(
        $operation['operator'],
        $number
      );
    }

    return $number;
  }

  final public static function fromFloatingPoint($packed) {
    if (ord($packed[0]) & 0x02 !== 0) {
      return new self(null, null);
    }

    $imaginary_as_real =
        strlen($packed) > FLOATING_POINT_REAL_LENGTH &&
        (ord($packed[FLOATING_POINT_REAL_LENGTH]) & 0x0c) !== 0
      ? self::fromFloatingPoint(substr($packed, FLOATING_POINT_REAL_LENGTH))
      : new self(null);
    $unpacked = unpack('C2exponent/H14mantissa', $packed);
    $real =
        ($unpacked['mantissa'][0] . '.' . substr($unpacked['mantissa'], 1)) *
        pow(10, $unpacked['exponent2'] - 0x80);

    return new self(
      $unpacked['exponent1'] & 0x80 !== 0 ? -$real : $real,
      $imaginary_as_real->real
    );
  }

  final public function isReal() {
    return $this->imaginary === null || $this->imaginary === 0;
  }

  final public function toExpression() {
    if ($this->real === null) {
      return null;
    } else if ($this->imaginary === null || $this->imaginary === 0.0) {
      return (string)$this->real;
    } else if ($this->imaginary === 1.0) {
      return $this->real === 0.0 ? 'i' : "$this->real+i";
    } else if ($this->imaginary === -1.0) {
      return $this->real === 0.0 ? '-i' : "$this->real-i";
    } else {
      $operator = $this->imaginary < 0 ? '' : '+';
      return $this->real === 0.0
        ? "{$this->imaginary}i"
        : "$this->real$operator{$this->imaginary}i";
    }
  }

  final public function toFloatingPoint($forceComplex = false) {
    if ($this->real === null) {
      return pack('C9', 0x02, 0, 0, 0, 0, 0, 0, 0, 0);
    }

    $exponent = $this->real !== 0 ? (int)log10($this->real) : 0;

    $mantissa = str_pad(
      str_replace('.', '', (string)($this->real / pow(10, $exponent))),
      14,
      '0'
    );

    $packed = pack(
      'C2H14',
      $this->real < 0 ? 0x80 : 0x00,
      $exponent + 0x80,
      $mantissa
    );

    if ($this->imaginary !== null && $this->imaginary !== 0 || $forceComplex) {
      $packed .=
          (new SimpleNumber($this->imaginary !== null ? $this->imaginary : 0))
          ->toFloatingPoint();
      $packed[0] = chr(ord($packed[0]) | 0x0c);
      $packed[FLOATING_POINT_REAL_LENGTH] =
          chr(ord($packed[FLOATING_POINT_REAL_LENGTH]) | 0x0c);
    }

    return $packed;
  }

  private function evaluateOperation($operator, $operand) {
    switch ($operator) {
      case '^':
        return new self(pow($this->real, $operand->real));
      case '*':
        return new self(
          $this->real * $operand->real -
              $this->imaginary * $operand->imaginary,
          $this->imaginary * $operand->real + $this->real * $operand->imaginary
        );
      case '/':
        $denominator =
            $operand->real * $operand->real +
            $operand->imaginary * $operand->imaginary;

        return new self(
          (
            $this->real * $operand->real +
                $this->imaginary * $operand->imaginary
          ) / $denominator,
          (
            $this->imaginary * $operand->real -
                $this->real * $operand->imaginary
          ) / $denominator
        );
      case '+':
        return new self(
          $this->real + $operand->real,
          $this->imaginary + $operand->imaginary
        );
      case '-':
        return new self(
          $this->real - $operand->real,
          $this->imaginary - $operand->imaginary
        );
    }
  }
}
?>
