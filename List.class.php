<?php
namespace ClrHome;

include_once(__DIR__ . '/SimpleNumber.class.php');
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
      ? FLOATING_POINT_REAL_LENGTH * 2
      : FLOATING_POINT_REAL_LENGTH;

    if ($list_length * $number_length + 2 > strlen($data)) {
      throw new \OutOfBoundsException(
        'List length exceeds variable data length'
      );
    }

    $list->setElements(array_map(
      array(SimpleNumber::class, 'fromFloatingPoint'),
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
      if (!$element->isReal()) {
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
        return $data . $element->toFloatingPoint($complex);
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
      $this->elements[] = new SimpleNumber(0);
    }

    $this->elements[$index] = SimpleNumber::from($value);
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
   * Returns the elements as `SimpleNumber`s.
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
        return $element->toExpression();
      },
      $this->elements
    );
  }

  /**
   * Sets the elements as numbers, `SimpleNumber`s, or evaluable expressions.
   * @param array<SimpleNumber|number|string> $elements The elements to set.
   */
  public function setElements($elements) {
    unset($this[0]);

    foreach ($elements as $element) {
      $this[] = $element;
    };
  }
}
?>
