<?php
namespace ClrHome;

define('ClrHome\SIGNER_HEX_CHUNK_LENGTH', 7);

define(
  'ClrHome\SIGNER_KEY_0104_N',
  '7099684781585961008970869750747020123422241021784080798173207130796800724845874359586492258503043165630748967766441275670874748414633441830936711089169581'
);

define(
  'ClrHome\SIGNER_KEY_0104_P',
  '13008861940417978637584514274743203260316419139111920661532489703530863799899'
);

define(
  'ClrHome\SIGNER_KEY_0104_Q',
  '545757562352748437778195250781205191227156621952501599157677289463253105178519'
);

include_once(__DIR__ . '/common.php');

class RabinSignature extends Immutable {
  protected $f;
  protected $hash;

  public function __construct($hash, $f) {
    $this->f = $f;
    $this->hash = $hash;
  }
}

/**
 * A Rabin cryptosystem utility class.
 */
class Rabin {
  private $n = null;
  private $p = null;
  private $q = null;

  private static function bcdechex($dec) {
    $hex = '';
    $hex_chunk_divisor = pow(16, SIGNER_HEX_CHUNK_LENGTH);

    while (bccomp($dec, 0) !== 0) {
      $hex = str_pad(
        dechex(bcmod($dec, $hex_chunk_divisor)),
        SIGNER_HEX_CHUNK_LENGTH,
        '0',
        STR_PAD_LEFT
      ) . $hex;

      $dec = bcdiv($dec, $hex_chunk_divisor);
    }

    $hex = ltrim($hex, '0');
    return $hex === '' ? '0' : $hex;
  }

  private static function bchexdec($hex) {
    $dec = '0';

    for (
      $hex_index = 0;
      $hex_index < strlen($hex);
      $hex_index += SIGNER_HEX_CHUNK_LENGTH
    ) {
      $dec = bcadd($dec, bcmul(
        hexdec(substr(
          $hex,
          -$hex_index - SIGNER_HEX_CHUNK_LENGTH,
          $hex_index + SIGNER_HEX_CHUNK_LENGTH > strlen($hex)
            ? -$hex_index
            : SIGNER_HEX_CHUNK_LENGTH
        )),
        bcpow(16, $hex_index)
      ));
    }

    return $dec;
  }

  private static function bclegendre($a, $p) {
    $value = bcpowmod($a, bcdiv(bcsub($p, 1), 2), $p);
    return bccomp($value, 1) === 1 ? -1 : (int)$value;
  }

  private static function reverseEndianness($hex) {
    return implode(array_reverse(str_split($hex, 2)));
  }

  private static function transformHash($hash) {
    return
        bcadd(bcmul(self::bchexdec(self::reverseEndianness($hash)), 256), 1);
  }

  private static function validateBigInt($string, $description) {
    if (!preg_match('/^\d+$/', $string)) {
      throw new \OutOfBoundsException("Invalid $description $string");
    }

    return $string;
  }

  final public function setPrivateKeyAsBigEndianHex($p, $q) {
    $this->setPrivateKeyAsDec(self::bchexdec($p), self::bchexdec($q));
  }

  final public function setPrivateKeyAsDec($p, $q) {
    $this->p = self::validateBigInt($p, 'private key p');
    $this->q = self::validateBigInt($q, 'private key q');

    if ($this->n === null) {
      $this->setPublicKeyAsDec(bcmul($p, $q));
    } else {
      $this->validateKeys();
    }
  }

  final public function setPrivateKeyAsLittleEndianHex($p, $q) {
    $this->setPrivateKeyAsBigEndianHex(
      self::reverseEndianness($p),
      self::reverseEndianness($q)
    );
  }

  final public function setPublicKeyAsBigEndianHex($n) {
    $this->setPublicKeyAsDec(self::bchexdec($n));
  }

  final public function setPublicKeyAsDec($n) {
    $this->n = self::validateBigInt($n, 'public key');
    $this->validateKeys();
  }

  final public function setPublicKeyAsLittleEndianHex($n) {
    $this->setPublicKeyAsBigEndianHex(self::reverseEndianness($n));
  }

  final public function sign($hash) {
    if ($this->p === null || $this->q === null) {
      throw new \BadMethodCallException(
        'Private key must be set prior to signing.'
      );
    }

    $hash_transformed = self::transformHash($hash);
    $p_nonresidue = self::bclegendre($hash_transformed, $this->p) !== 1;
    $q_residue = self::bclegendre($hash_transformed, $this->q) === 1;
    $f = (int)$p_nonresidue + (int)$q_residue * 2;
    $hash_complete = self::applyF($hash_transformed, $f);
    $sqrt_mod_p =
        bcpowmod($hash_complete, bcdiv(bcadd($this->p, 1), 4), $this->p);
    $sqrt_mod_q =
        bcpowmod($hash_complete, bcdiv(bcadd($this->q, 1), 4), $this->q);
    $sqrt_distance = bcsub($sqrt_mod_p, $sqrt_mod_q);

    while (bccomp($sqrt_distance, 0) === -1) {
      $sqrt_distance = bcadd($sqrt_distance, $this->p);
    }

    return new RabinSignature(
      self::reverseEndianness(self::bcdechex(bcadd(bcmul(bcmod(bcmul(
        $sqrt_distance,
        bcpowmod($this->q, bcsub($this->p, 2), $this->p)
      ), $this->p), $this->q), $sqrt_mod_q))),
      $f
    );
  }

  final public function verify($hash, $signature) {
    if ($this->n === null) {
      throw new \BadMethodCallException(
        'Public key must be set prior to verifying.'
      );
    }

    return bccomp(
      self::applyF(self::transformHash($hash), $signature->f),
      bcpowmod(
        self::bchexdec(self::reverseEndianness($signature->hash)),
        2,
        $this->n
      )
    ) === 0;
  }

  private function applyF($hash_transformed, $f) {
    switch ($f) {
      case 0:
        return bcsub($this->n, bcadd($hash_transformed, $hash_transformed));
      case 1:
        return bcsub($this->n, $hash_transformed);
      case 2:
        return $hash_transformed;
      case 3:
        return bcadd($hash_transformed, $hash_transformed);
    }
  }

  private function validateKeys() {
    if (
      $this->n !== null && $this->p !== null && $this->q !== null &&
          bccomp(bcmul($this->p, $this->q), $this->n) !== 0
    ) {
      throw new \OutOfBoundsException('Public and private keys do not match');
    }
  }
}
?>
