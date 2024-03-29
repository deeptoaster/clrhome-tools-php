<?php
namespace ClrHome;

include_once(__DIR__ . '/SimpleNumber.class.php');
include_once(__DIR__ . '/Variable.class.php');

/**
 * A real matrix.
 */
class Matrix extends Variable implements \ArrayAccess {
  private $elements = array();

  final protected static function fromEntry($type, $name, $data) {
    $matrix = new static();
    $matrix->name = str_pad($name, 2, "\x00");

    if (strlen($data) < 2) {
      throw new \OutOfBoundsException('Matrix contents not found');
    }

    $column_count = ord($data[0]);
    $row_count = ord($data[1]);

    if (
      $column_count * $row_count * FLOATING_POINT_REAL_LENGTH + 2 >
          strlen($data)
    ) {
      throw new \OutOfBoundsException(
        'Matrix length exceeds variable data length'
      );
    }

    $matrix->setElements(array_chunk(array_map(
      function($element) {
        return SimpleNumber::fromFloatingPoint($element)->real;
      },
      str_split(
        substr(
          $data,
          2,
          $column_count * $row_count * FLOATING_POINT_REAL_LENGTH
        ),
        FLOATING_POINT_REAL_LENGTH
      )
    ), $column_count));

    return $matrix;
  }

  /**
   * Returns the matrix name detokenized to ASCII.
   */
  final public function getName() {
    return isset($this->name)
      ? '[' . chr(ord($this->name[1]) + 0x41) . ']'
      : null;
  }

  /**
   * Sets the list name as a single token as an ASCII string.
   * @param string $name One of '[A]' through '[J]'.
   */
  final public function setName($name) {
    if (!preg_match('/^\[[A-J]\]$/', $name)) {
      throw new \InvalidArgumentException("Invalid matrix name $name");
    }

    $this->name = pack('C2', 0x5c, ord($name[1]) - 0x41);
  }

  final public function getType() {
    return VariableType::MATRIX;
  }

  final protected function getData() {
    if (count($this->elements) === 0 || count($this->elements[0]) === 0) {
      throw new \BadFunctionCallException(
        'Matrix must not be empty at export'
      );
    }

    return array_reduce($this->elements, function($data, $row) {
      return array_reduce($row, function($data, $element) {
        return $data . (new SimpleNumber($element))->toFloatingPoint();
      }, $data);
    }, pack('C2', count($this->elements[0]), count($this->elements)));
  }

  public function offsetExists($index) {
    list($row, $column) = self::validateIndex($index);
    return $row < count($this->elements) &&
        $column < count($this->elements[0]);
  }

  public function offsetGet($index) {
    list($row, $column) = self::validateIndex($index);
    return $this->elements[$row][$column];
  }

  public function offsetSet($index, $value) {
    if ($index === null) {
      $row = count($this->elements);
      $column = $row !== 0 ? count($this->elements[0]) : 0;
    } else {
      list($row, $column) = self::validateIndex($index);
    }

    for ($row_index = 0; $row_index <= $row; $row_index++) {
      if (!isset($this->elements[$row_index])) {
        $this->elements[] = array();
      }

      while (count($this->elements[$row_index]) <= $column) {
        $this->elements[$row_index][] = 0;
      }
    }

    $this->elements[$row][$column] = SimpleNumber::from($value)->real;
  }

  public function offsetUnset($index) {
    list($row, $column) = self::validateIndex($index);
    $this->elements = array_slice($this->elements, 0, $row);

    for ($row_index = 0; $row_index < $row; $row_index++) {
      $this->elements[$row_index] =
          array_slice($this->elements[$row_index], 0, $column);
    }
  }

  private static function validateIndex($index) {
    if (!preg_match('/^(\d+),(\d+)$/', $index, $match)) {
      throw new \OutOfRangeException(
        "Matrix index $index must be integers in the form row,column"
      );
    }

    return array((int)$match[1], (int)$match[2]);
  }

  /**
   * Returns the elements in row-major order.
   */
  public function getElements() {
    return $this->elements;
  }

  /**
   * Sets the elements as numbers, `SimpleNumber`s, or evaluable expressions.
   * @param array<array<SimpleNumber|number|string>> $elements The elements.
   */
  public function setElements($elements) {
    unset($this['0,0']);
    $column_count = null;

    foreach ($elements as $row_index => $row) {
      if ($column_count === null) {
        $column_count = count($row);
      } else if ($column_count !== count($row)) {
        throw new \UnderflowException(
          'Each row in a matrix must have the same number of elements'
        );
      }

      foreach ($row as $column_index => $element) {
        $this["$row_index,$column_index"] = $element;
      }
    };
  }
}
?>
