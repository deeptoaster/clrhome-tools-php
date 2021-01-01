<?
namespace ClrHome;

include(__DIR__ . '/common.php');

abstract class Variable {
  private $archived = false;
  private $series = Series::TI83P;
  private $version = 0;

  abstract protected function getData();

  abstract protected function getName();

  abstract protected function getType();

  final public function getArchived() {
    return $this->archived;
  }

  final public function setArchived($archived) {
    $this->archived = (bool)$archived;
  }

  final public function getSeries() {
    return $this->series;
  }

  final public function setSeries($series) {
    $this->series = Series::validate($series);
  }

  final public function getVersion() {
    return $this->version;
  }

  final public function setVersion($version) {
    $this->version = (int)$version;
  }

  final protected function getEntry($series = null) {
    $data = $this->getData();

    switch ($series !== null ? $series : $this->series) {
      case Series::TI83:
        return pack(
          'v2Ca8va*',
          0x0b,
          strlen($data),
          $this->getType(),
          $this->getName(),
          strlen($data),
          $data
        );
      case Series::TI83P:
        return pack(
          'v2Ca8C2va*',
          0x0d,
          strlen($data),
          $this->getType(),
          $this->getName(),
          $this->version,
          $this->archived ? 0x80 : 0x00,
          strlen($data),
          $data
        );
    }
  }
}
