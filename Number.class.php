<?php
namespace ClrHome;

include_once(__DIR__ . '/SimpleNumber.class.php');
include_once(__DIR__ . '/Variable.class.php');

/**
 * A real or complex number.
 */
class Number extends Variable {
  private $number;

  public function __construct() {
    $this->number = new SimpleNumber();
  }

  final protected static function fromEntry($type, $name, $data) {
    $number = new static();
    $number->name = $name;

    if (strlen($data) < 9) {
      throw new \OutOfBoundsException('Number contents not found');
    }

    $number->number = SimpleNumber::fromFloatingPoint($data);
    return $number;
  }

  /**
   * Returns the variable name as a single token detokenized to ASCII.
   */
  final public function getName() {
    return isset($this->name)
      ? $this->name === '[' ? 'theta' : $this->name
      : null;
  }

  /**
   * Sets the variable name as a single token as an ASCII string.
   * @param string $name The variable name as an ASCII string.
   */
  final public function setName($name) {
    if (!preg_match('/^([A-Z\[]|theta)$/', $name)) {
      throw new \InvalidArgumentException(
        "Name $name must be a single uppercase letter or theta"
      );
    }

    $this->name = $name === 'theta' ? '[' : $name;
  }

  final public function getType() {
    return !$this->number->isReal()
      ? VariableType::COMPLEX
      : VariableType::REAL;
  }

  final protected function getData() {
    return $this->number->toFloatingPoint();
  }

  /**
   * Returns the number as a `SimpleNumber`.
   */
  public function get() {
    return $this->number;
  }

  /**
   * Returns the number as an evaluable expression.
   */
  public function getAsExpression() {
    return $this->number->toExpression();
  }

  /**
   * Sets the number as a number, `SimpleNumber`, or evaluable expression.
   * @param SimpleNumber|number|string The value to set.
   */
  public function set($value) {
    $this->number = SimpleNumber::from($value);
  }

  /**
   * Returns the imaginary component.
   */
  public function getImaginary() {
    return $this->number->imaginary;
  }

  /**
   * Sets the imaginary component.
   * @param float|null $imaginary The imaginary component, or null.
   */
  public function setImaginary($imaginary) {
    if (!is_numeric($imaginary) && $imaginary !== null) {
      throw new \InvalidArgumentException(
        "Imaginary component $imaginary must be a number or null"
      );
    }

    $this->number = new SimpleNumber($this->number->real, $imaginary);
  }

  /**
   * Returns the real component.
   */
  public function getReal() {
    return $this->number->real;
  }

  /**
   * Sets the real component.
   * @param float|null $real The real component, or null.
   */
  public function setReal($real) {
    if (!is_numeric($real) && $real !== null) {
      throw new \InvalidArgumentException(
        "Real component $real must be a number or null"
      );
    }

    $this->number = new SimpleNumber($real, $this->number->imaginary);
  }
}
?>
