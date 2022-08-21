<?php
namespace ClrHome;

define('ClrHome\FLOATING_POINT_REAL_LENGTH', 9);
define('ClrHome\PARENTHESES_PRECEDENCE', 16);
define('ClrHome\UNARY_OPERATOR_PRECEDENCE', 3);

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

  final public static function from(
    $value,
    $substitutions = array(),
    $use_caret_as_xor = false
  ) {
    if (is_string($value)) {
      return self::fromExpression($value, array_merge(
        array(
          'PI' => new self(M_PI),
          'E' => new self(M_E),
          'I' => new self(0, 1)
        ),
        array_combine(
          array_map('strtoupper', array_keys($substitutions)),
          array_values($substitutions)
        )
      ), $use_caret_as_xor);
    } else if (is_numeric($value) || $value === null) {
      return new self($value);
    } else if (is_a($value, self::class)) {
      return $value;
    } else {
      throw new \InvalidArgumentException(sprintf(
        "Source must be a number, expression, or SimpleNumber, not %s",
        gettype($value)
      ));
    }
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

  private static function fromExpression(
    $expression,
    $substitutions,
    $use_caret_as_xor
  ) {
    $unary_operators = array('+', '-', '!', '~');

    $binary_operators = array(
      '<=>' => 8,
      '<<' => 7,
      '>>' => 7,
      '<=' => 9,
      '>=' => 9,
      '==' => 10,
      '!=' => 10,
      '&&' => 14,
      '||' => 15,
      '^' => 4,
      '*' => 5,
      '/' => 5,
      '%' => 5,
      '+' => 6,
      '-' => 6,
      '<' => 9,
      '>' => 9,
      '=' => 10,
      '&' => 11,
      '|' => 13
    );

    if ($use_caret_as_xor) {
      $binary_operators['^'] = 12;
    }

    $binary_operator_pattern = self::pregQuoteKeys($binary_operators);
    $number = new self();
    $precedence = 0;
    $stack = array();
    $token_start = 0;

    while ($token_start !== strlen($expression)) {
      $implicit_multiplication = false;
      $operator = null;
      $valid = false;
      $character = $expression[$token_start];

      if ($character === '(') {
        if (!$number->isEmpty()) {
          $implicit_multiplication = true;
        } else {
          $precedence -= PARENTHESES_PRECEDENCE;
          $token_start++;
          $valid = true;
        }
      } else if ($character === ')') {
        if ($number->isEmpty()) {
          throw new \UnexpectedValueException('Operand expected before )');
        }

        $precedence += PARENTHESES_PRECEDENCE;

        while (
          count($stack) !== 0 &&
              $precedence >= $stack[count($stack) - 1]['precedence']
        ) {
          $operation = array_pop($stack);

          $number = $operation['number']->evaluateOperation(
            $operation['operator'],
            $number,
            $use_caret_as_xor
          );
        }

        $token_start++;
        $valid = true;
      } else if (preg_match(
        '/\G(([01]+)b|%([01]+)|([0-7]+)o|([\da-f]+)h|\$([\da-f]+)|(\'\\\\\'\'|\'\\\\\\\\\'|\'[^\\\\\']\')|((\d*\.)?\d+(e[+-]?(\d+))?))/i',
        $expression,
        $matches,
        null,
        $token_start
      )) {
        if (!$number->isEmpty()) {
          throw new \UnexpectedValueException(
            "Operator expected at $matches[0]"
          );

          break;
        }

        $number = new self(
          $matches[2] !== '' || $matches[3] !== ''
            ? bindec($matches[2] . @$matches[3])
            : (
              $matches[4] !== ''
                ? octdec($matches[4])
                : (
                  $matches[5] !== '' || $matches[6] !== ''
                    ? hexdec($matches[5] . @$matches[6])
                    : ($matches[7] !== '' ? ord($matches[7][1]) : $matches[8])
                )
            )
        );

        $token_start += strlen($matches[0]);
        $valid = true;
      } else if (preg_match(
        '/\G\$|\G\w+/',
        $expression,
        $matches,
        null,
        $token_start
      )) {
        if (!$number->isEmpty()) {
          $implicit_multiplication = true;
        } else {
          $key = strtoupper($matches[0]);

          if (!array_key_exists($key, $substitutions)) {
            throw new \UnexpectedValueException("Undefined value $matches[0]");
          }

          $value = $substitutions[$key];
          $number = is_string($value)
            ? self::fromExpression($value, $substitutions, $use_caret_as_xor)
            : self::from($value);
          $token_start += strlen($matches[0]);
          $valid = true;
        }
      } else if (in_array($character, $unary_operators)) {
        $operator = $character;
      } else if (preg_match(
        $binary_operator_pattern,
        $expression,
        $matches,
        null,
        $token_start
      )) {
        $operator = $matches[0];
      } else if (preg_match('/\s/', $character)) {
        $token_start++;
        $valid = true;
      }

      if ($implicit_multiplication) {
        $operator = '*';
      }

      if ($operator !== null) {
        if ($number->isEmpty()) {
          if (!in_array($operator, $unary_operators)) {
            throw new \UnexpectedValueException(
              "Operand expected at $character"
            );
          }

          $number = new self(0);
          $operator_precedence = $precedence + UNARY_OPERATOR_PRECEDENCE;
        } else {
          if (!array_key_exists($operator, $binary_operators)) {
            throw new \UnexpectedValueException(
              "Binary operator expected at $character"
            );
          }

          $operator_precedence = $precedence + $binary_operators[$operator];
        }

        while (
          count($stack) !== 0 &&
              $operator_precedence >= $stack[count($stack) - 1]['precedence']
        ) {
          $operation = array_pop($stack);

          $number = $operation['number']->evaluateOperation(
            $operation['operator'],
            $number,
            $use_caret_as_xor
          );
        }

        $stack[] = array(
          'number' => $number,
          'operator' => $operator,
          'precedence' => $operator_precedence
        );

        $number = new self();
        $token_start += $implicit_multiplication ? 0 : strlen($operator);
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
        $number,
        $use_caret_as_xor
      );
    }

    return $number;
  }

  private static function pregQuoteKeys($options) {
    return '/\G(' . implode('|', array_map(
      'preg_quote',
      array_keys($options),
      array_fill(0, count($options), '/')
    )) . ')/';
  }

  final public function isEmpty() {
    return $this->real === null && $this->imaginary === null;
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
          (new self($this->imaginary !== null ? $this->imaginary : 0))
          ->toFloatingPoint();
      $packed[0] = chr(ord($packed[0]) | 0x0c);
      $packed[FLOATING_POINT_REAL_LENGTH] =
          chr(ord($packed[FLOATING_POINT_REAL_LENGTH]) | 0x0c);
    }

    return $packed;
  }

  private function evaluateOperation($operator, $operand, $use_caret_as_xor) {
    switch ($operator) {
      case '<=>':
        return new self(
          $this->real < $operand->real
            ? -1
            : ($this->real > $operand->real ? 1 : 0)
        );
      case '<<':
        return new self($this->real << $operand->real);
      case '>>':
        return new self($this->real >> $operand->real);
      case '<=':
        return new self($this->real <= $operand->real);
      case '>=':
        return new self($this->real >= $operand->real);
      case '==':
      case '=':
        return new self($this->real == $operand->real);
      case '!=':
        return new self($this->real != $operand->real);
      case '&&':
        return new self($this->real && $operand->real);
      case '||':
        return new self($this->real || $operand->real);
      case '^':
        return new self(
          $use_caret_as_xor
            ? $this->real ^ $operand->real
            : pow($this->real, $operand->real)
        );
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
      case '%':
        return new self($this->real % $operand->real);
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
      case '<':
        return new self($this->real < $operand->real);
      case '>':
        return new self($this->real > $operand->real);
      case '&':
        return new self($this->real & $operand->real);
      case '|':
        return new self($this->real | $operand->real);
    }
  }
}
?>
