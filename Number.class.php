<?
namespace ClrHome;

include_once(__DIR__ . '/Variable.class.php');

/**
 * A real or complex number.
 */
class Number extends Variable {
  private $imaginary = null;
  private $real = null;

  final protected static function fromEntry($type, $name, $data) {
    $number = new static();
    $number->name = $name;
    list($real, $imaginary) = parent::floatingPointToNumber($data);
    $number->setReal($real);
    $number->setImaginary($imaginary);
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
    return $this->imaginary !== null && $this->imaginary !== 0
      ? VariableType::COMPLEX
      : VariableType::REAL;
  }

  final protected function getData() {
    return parent::numberToFloatingPoint($this->real, $this->imaginary);
  }

  /**
   * Returns the number as an evaluable expression.
   */
  public function getAsExpression() {
    return "$this->real+{$this->imaginary}i";
  }

  /**
   * Sets the number as an evaluable expression.
   * @param string $expression The expression to evaluate.
   */
  public function setAsExpression($expression) {
    list($this->real, $this->imaginary) =
        parent::evaluateExpression($expression);
  }

  /**
   * Returns the imaginary component.
   */
  public function getImaginary() {
    return $this->imaginary;
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

    $this->imaginary = $imaginary;
  }

  /**
   * Returns the real component.
   */
  public function getReal() {
    return $this->real;
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

    $this->real = $real;
  }
}
?>
