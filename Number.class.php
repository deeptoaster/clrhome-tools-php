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

  final public function getData() {
    return parent::numberToFloatingPoint($this->real, $this->imaginary);
  }

  /**
   * Returns the appvar name as a character string.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Sets the appvar name as a character string.
   * @param string $name The appvar name as a character string.
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
    return $this->imaginary !== null
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
