<?
namespace ClrHome;

include_once(__DIR__ . '/RamVariable.class.php');

/**
 * A real matrix.
 */
class Matrix extends RamVariable implements \ArrayAccess {
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
      $column_count * $row_count * VARIABLE_REAL_LENGTH + 2 > strlen($data)
    ) {
      throw new \OutOfBoundsException(
        'Matrix length exceeds variable data length'
      );
    }

    $matrix->setElements(array_chunk(array_map(
      function($element) {
        return parent::floatingPointToNumber($element)[0];
      },
      str_split(
        substr($data, 2, $column_count * $row_count * VARIABLE_REAL_LENGTH),
        VARIABLE_REAL_LENGTH
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
        return $data . parent::numberToFloatingPoint($element);
      }, $data);
    }, pack('C2', count($this->elements[1]), count($this->elements)));
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

    if (is_string($value)) {
      $this->elements[$row][$column] = parent::expressionToNumber($value)[0];
    } else if (is_numeric($value) || $value === null) {
      $this->elements[$row][$column] = $value;
    } else {
      throw new \InvalidArgumentException(
        "Matrix element must be a number"
      );
    }
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
    if (!preg_match('/^(\d+),(\d+)$/', $index, $matches)) {
      throw new \OutOfRangeException(
        "Matrix index $index must be integers in the form row,column"
      );
    }

    return array((int)$matches[1], (int)$matches[2]);
  }

  /**
   * Returns the elements in row-major order as component tuples.
   */
  public function getElements() {
    return $this->elements;
  }

  /**
   * Returns the elements in row-major order as evaluable expressions.
   */
  public function getElementsAsExpressions() {
    return array_map(function($row) {
      return array_map(array(parent::class, 'numberToExpression'), $row);
    }, $this->elements);
  }

  /**
   * Sets the elements as numbers or evaluable expressions.
   * @param array<array<number|string>> $elements The elements to set.
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
