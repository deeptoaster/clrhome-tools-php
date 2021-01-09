<?
namespace ClrHome;

include_once(__DIR__ . '/Variable.class.php');

/**
 * A real or complex number.
 */
class Number extends Variable {
  private $imaginary = null;
  private $name;
  private $real = null;

  final protected static function fromEntry($type, $name, $data) {
    $number = new static();
    $number->setName($name);
    list($real, $imaginary) = self::floatingPointToNumber($data);
    $number->setReal($real);
    $number->setImaginary($imaginary);
    return $number;
  }

  final public function getData() {
    return parent::numberToFloatingPoint($this->real, $this->imaginary);
  }

  /**
   * Returns the variable name as a single token.
   */
  public function getName() {
    return $this->name === '[' ? 'theta' : $this->name;
  }

  /**
   * Sets the variable name as a single token.
   * @param string $name The variable name as a single token.
   */
  public function setName($name) {
    if (!preg_match('/^([A-Z\[]|theta)$/', $name)) {
      throw new \InvalidArgumentException(
        "Name $name must be a single uppercase letter or theta."
      );
    }

    $this->name = $name === 'theta' ? '[' : $name;
  }

  final public function getType() {
    return $this->imaginary !== null && $this->imaginary !== 0
      ? VariableType::COMPLEX
      : VariableType::REAL;
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
    $this->real = $real;
  }
}
?>
