<?
namespace ClrHome;

include_once(__DIR__ . '/Variable.class.php');

class AppVar extends Variable {
  private $body = '';
  private $name;

  final public function getData() {
    $body = $this->getBody();
    return pack('va*', strlen($body), $body);
  }

  public function getName() {
    return $this->name;
  }

  public function setName($name) {
    $this->name = substr($name, 0, 8);
  }

  final public function getType() {
    return VariableType::APPVAR;
  }

  public function getBody() {
    return $this->body;
  }

  public function setBody($body) {
    $this->body = $body;
  }
}
?>
