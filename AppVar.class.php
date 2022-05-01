<?php
namespace ClrHome;

include_once(__DIR__ . '/Variable.class.php');

/**
 * A TI appvar.
 */
class AppVar extends Variable {
  private $contents = '';

  final protected static function fromEntry($type, $name, $data) {
    $appvar = new static();
    $appvar->name = $name;

    if (strlen($data) < 2) {
      throw new \OutOfBoundsException('Appvar contents not found');
    }

    $contents_length = parent::readWord($data, 0);

    if ($contents_length + 2 > strlen($data)) {
      throw new \OutOfBoundsException(
        'Appvar content length exceeds variable data length'
      );
    }

    $appvar->setContents(substr($data, 2, $contents_length));
    return $appvar;
  }

  /**
   * Returns the appvar name as a character string.
   */
  final public function getName() {
    return isset($this->name) ? $this->name : null;
  }

  /**
   * Sets the appvar name as a character string.
   * @param string $name The appvar name as a character string.
   */
  final public function setName($name) {
    $this->name = substr($name, 0, 8);
  }

  final public function getType() {
    return VariableType::APPVAR;
  }

  final protected function getData() {
    $contents = $this->getContents();
    return pack('va*', strlen($contents), $contents);
  }

  /**
   * Returns the appvar contents.
   */
  public function getContents() {
    return $this->contents;
  }

  /**
   * Sets the appvar contents.
   * @param string $contents The appvar contents to set.
   */
  public function setContents($contents) {
    $this->contents = $contents;
  }
}
?>
