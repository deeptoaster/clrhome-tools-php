<?
namespace ClrHome;

include_once(__DIR__ . '/Variable.class.php');

/**
 * A real or complex list.
 */
class ListVariable extends Variable implements \ArrayAccess {
  private $elements = array();

  final protected static function fromEntry($type, $name, $data) {
    $list = new static();
    $list->name = str_pad($name, 2, "\x00");

    if (strlen($data) < 2) {
      throw new \OutOfBoundsException('List contents not found');
    }

    $list_length = parent::readWord($data, 0);
    $number_length = $type === VariableType::LIST_COMPLEX
      ? VARIABLE_REAL_LENGTH * 2
      : VARIABLE_REAL_LENGTH;

    if ($list_length * $number_length + 2 > strlen($data)) {
      throw new \OutOfBoundsException(
        'List length exceeds variable data length'
      );
    }

    $list->setElements(array_map(
      array(parent::class, 'floatingPointToNumber'),
      str_split(
        substr($data, 2, $list_length * $number_length),
        $number_length
      )
    ));

    return $list;
  }

  /**
   * Returns the list name detokenized to ASCII.
   */
  final public function getName() {
    return isset($this->name)
      ? ord($this->name[1]) < 0x06
        ? 'L' . (string)(ord($this->name[1]) + 0x01)
        : '|L' . str_replace('[', 'theta', substr($this->name, 1))
      : null;
  }

  /**
   * Sets the list name as an ASCII string.
   * @param string $name Either 'L1' through 'L6' or a name starting with '|L'.
   */
  final public function setName($name) {
    if (
      !preg_match('/^(L[1-6]|\|L([A-Z\[]|theta)([0-9A-Z\[]|theta)*)$/', $name)
    ) {
      throw new \InvalidArgumentException("Invalid list name $name");
    }

    $this->name = $name[0] === 'L'
      ? pack('C2', 0x5d, (int)$name[1] - 0x01)
      : pack('Ca*', 0x5d, substr(str_replace('theta', '[', $name), 2, 5));
  }

  final public function getType() {
    $complex = false;

    foreach ($this->elements as $element) {
      if ($element[1] !== null && $element[1] !== 0) {
        $complex = true;
        break;
      }
    }

    return $complex
      ? VariableType::LIST_COMPLEX
      : VariableType::LIST_REAL;
  }

  final protected function getData() {
    if (count($this->elements) === 0) {
      throw new \BadFunctionCallException('List must not be empty at export');
    }

    $complex = $this->getType() === VariableType::LIST_COMPLEX;

    return array_reduce(
      $this->elements,
      function($data, $element) use ($complex) {
        return $data . parent::numberToFloatingPoint(
          $element[0],
          $element[1],
          $complex
        );
      },
      pack('v', count($this->elements))
    );
  }

  public function offsetExists($index) {
    self::validateIndex($index);
    return $index < count($this->elements);
  }

  public function offsetGet($index) {
    self::validateIndex($index);
    return $this->elements[$index];
  }

  public function offsetSet($index, $value) {
    if ($index === null) {
      $index = count($this->elements);
    }

    self::validateIndex($index);

    while (count($this->elements) < $index) {
      $this->elements[] = array(0, null);
    }

    if (is_string($value)) {
      $this->elements[$index] = parent::expressionToNumber($value);
    } else if (is_numeric($value) || $value === null) {
      $this->elements[$index] = array($value, null);
    } else if (is_array($value)) {
      if (
        count($value) > 2 ||
            count($value) >= 1 &&
            !is_numeric($value[0]) &&
            $value[0] !== null ||
            count($value) >= 2 &&
            !is_numeric($value[1]) &&
            $value[1] !== null
      ) {
        throw new \InvalidArgumentException(
          "List element must be a number or tuple of real and imaginary components"
        );
      }

      $this->elements[$index] = array(
        isset($value[0]) ? $value[0] : null,
        isset($value[1]) ? $value[1] : null
      );
    } else {
      throw new \InvalidArgumentException(
        "List element must be a number or tuple of real and imaginary components"
      );
    }
  }

  public function offsetUnset($index) {
    self::validateIndex($index);
    $this->elements = array_slice($this->elements, 0, $index);
  }

  private static function validateIndex($index) {
    if ($index < 0 || $index !== (int)$index) {
      throw new \OutOfRangeException(
        "List index $index must be a nonnegative integer"
      );
    }
  }

  /**
   * Returns the elements as component tuples.
   */
  public function getElements() {
    return $this->elements;
  }

  /**
   * Returns the elements as evaluable expressions.
   */
  public function getElementsAsExpressions() {
    return array_map(
      function($element) {
        return parent::numberToExpression($element[0], $element[1]);
      },
      $this->elements
    );
  }

  /**
   * Sets the elements as numbers, component tuples, or evaluable expressions.
   * @param array<array<number>|number|string> $elements The elements to set.
   */
  public function setElements($elements) {
    unset($this[0]);

    foreach ($elements as $element) {
      $this[] = $element;
    };
  }
}
?>
