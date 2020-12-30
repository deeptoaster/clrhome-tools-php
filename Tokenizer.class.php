<?
namespace ClrHome;

// TODO: move from conf.xml to catalog.xml
define('TOKENIZER_CATALOG_URL', 'https://clrhome.org/catalog/conf.xml');

abstract class Enum {
  public static function getConstList() {
    static $const_list = null;

    if ($const_list === null) {
      $const_list = array();
      $reflection = new \ReflectionClass(static::class);

      while ($reflection !== false) {
        $const_list = array_merge($reflection->getConstants(), $const_list);
        $reflection = $reflection->getParentClass();
      }
    }

    return $const_list;
  }

  public static function validate($value) {
    if (!in_array($value, static::getConstList())) {
      throw new \InvalidArgumentException(
        sprintf('Invalid value for %s: %s', static::class, $value)
      );
    }

    return $value;
  }
}

abstract class Series extends Enum {
  const TI83 = '**TI83**';
  const TI83P = '**TI83F*';
}

abstract class Language extends Enum {
  const AXE = 'axe';
  const BASIC = '';
  const GRAMMER = 'grammer';
}

class Tokenizer {
  protected $catalog;
  protected $inverseCatalog;
  protected $language = Language::BASIC;
  protected $series = Series::TI83P;

  public function __construct($catalog_file = __DIR__ . '/catalog.xml') {
    if (!file_exists($catalog_file)) {
      $catalog_handle = fopen(TOKENIZER_CATALOG_URL, 'r');

      if ($catalog_handle === false) {
        throw new \OutOfBoundsException(
          'Unable to download catalog file at ' . TOKENIZER_CATALOG_URL
        );
      }

      if (file_put_contents($catalog_file, $catalog_handle) === false) {
        throw new \OutOfBoundsException(
          "Unable to write catalog file at $catalog_file"
        );
      }
    }

    $this->catalog = simplexml_load_file($catalog_file);
    $namespaces = $this->catalog->getDocNamespaces();

    if ($this->catalog === false) {
      throw new \OutOfBoundsException(
        "Unable to read catalog file at $catalog_file"
      );
    }

    $token_idx = 0;

    foreach ($this->catalog->children() as $token) {
      switch ($token->getName()) {
        case 'table':
          $subtoken_idx = 0;

          foreach ($token->children() as $subtoken) {
            $this->registerToken(
              $namespaces,
              $subtoken,
              $token_idx,
              $subtoken_idx
            );

            $subtoken_idx++;
          }

          break;
        case 'token':
          $this->registerToken(
            $namespaces,
            $token,
            $token_idx
          );

          break;
      }

      $token_idx++;
    }
  }

  public function tokenize($chars) {
    $tokens = '';
    $chars = str_replace("\xe2\x86\x92", '->', $chars);
    $line_start = 0;

    while ($line_start < strlen($chars)) {
      $newline_position = strpos($chars, "\n", $line_start);
      $slash_position = strpos($chars, "\\", $line_start);

      $line_end = min(
        $newline_position !== false ? $newline_position : strlen($chars),
        $slash_position !== false ? $slash_position : strlen($chars)
      );

      $line = substr($chars, $line_start, $line_end - $line_start);
      $token_stack = array();
      $token_start = $line_start;
      $line_tokens = array();
      $visited = array();

      while ($token_start !== $line_end) {
        if (isset($token_stack[$token_start])) {
          if (count($token_stack[$token_start]) === 0) {
            if (empty($token_stack)) {
              break;
            } else {
              $visited[$token_start] = true;
              array_pop($token_stack);
              $token_start -= strlen(array_pop($line_tokens));
            }
          } else {
            $token = array_shift($token_stack[$token_start]);
            $token_end = $token_start + strlen($token);

            if (!isset($visited[$token_end])) {
              $line_tokens[] = $token;
              $token_start = $token_end;
            }
          }
        } else {
          $token_stack[$token_start] = array();

          for (
            $token_length = min($line_end - $token_start, 14);
            $token_length > 0;
            $token_length--
          ) {
            $token = substr($chars, $token_start, $token_length);

            if (isset($this->inverseCatalog[":$token"])) {
              $token_stack[$token_start][] = $token;
            }
          }
        }
      }

      if ($token_start === $line_start) {
        throw new \UnexpectedValueException("Unable to parse token near $line");
      }

      $tokens = array_reduce($line_tokens, function($tokens, $token) {
        return $tokens . $this->inverseCatalog[":$token"];
      }, $tokens);

      if ($line_end < strlen($chars) && $chars[$line_end] === "\n") {
        $tokens .= $this->inverseCatalog[":\n"];
      }

      $line_start = $line_end + 1;
    }

    return $tokens;
  }

  public function getLanguage() {
    return $this->language;
  }

  public function setLanguage($language) {
    $this->language = Language::validate($language);
  }

  public function getSeries() {
    return $this->series;
  }

  public function setSeries($series) {
    $this->series = Series::validate($series);
  }

  private function registerToken(
    $namespaces,
    $token,
    $token_idx,
    $subtoken_idx = null
  ) {
    foreach ($namespaces as $prefix => $namespace) {
      $id = $token->attributes($prefix !== '' ? $namespace : '')['id'];

      if ($id !== null) {
        $this->inverseCatalog[":$id"] = chr($token_idx) . (
          $subtoken_idx !== null ? chr($subtoken_idx) : ''
        );
      }
    }
  }
}
?>
