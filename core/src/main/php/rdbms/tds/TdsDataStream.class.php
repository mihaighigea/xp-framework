<?php
/* This class is part of the XP framework
 *
 * $Id$ 
 */

  uses('rdbms.tds.TdsProtocolException');

  /**
   * A TDS data stream
   *
   * @see     xp://rdbms.tds.TdsV7Protocolo#read
   * @test    xp://net.xp_framework.unittest.rdbms.tds.TdsDataStreamTest
   */
  class TdsDataStream extends Object {
    protected $pkt= 0;
    protected $sock= NULL;
    protected $buffer= '';
    protected $header= array('status' => 0, 'length' => -1);

    /**
     * Helper method for dump()
     *
     * @param   string bytes
     * @param   int offset
     * @return  string
     */
    protected static function chars($bytes, $offset) {
      $s= '';
      for ($j= $offset- 16, $l= min($offset, strlen($bytes)); $j < $l; $j++) {
        $c= $bytes{$j};
        $s.= $c < "\x20" || $c > "\x7F" ? ' ' : $c;
      }
      return $s;
    }

    /**
     * Creates a hexdump
     *
     * @param   string bytes
     * @return  string
     */
    protected static function dump($bytes) {
      $n= strlen($bytes);
      if ($r= ($n % 16)) {
        $bytes.= str_repeat("\x00", 16 - $r);
      }
      
      $s= '';
      for ($i= 0; $i < $n; $i++) {
        if (0 === $i) {
          $s= '  0: ';
        } else if (0 === ($i % 16)) {
          $s.= sprintf("|%s|\n%3d: ", self::chars($bytes, $i), $i);
        }
        $s.= sprintf('%02X ', ord($bytes{$i}));
      }
      return $s;
    }

    /**
     * Constructor
     *
     * @param   peer.Socket sock
     */
    public function __construct($sock) {
      $this->sock= $sock;
    }
    
    /**
     * Connect
     *
     */
    public function connect() {
      $this->sock->isConnected() || $this->sock->connect();
    }
    
    /**
     * Close
     *
     */
    public function close() {
      $this->sock->isConnected() && $this->sock->close();
    }

    /**
     * Protocol write
     *
     * @param   int type the message type one of the MSG_* constants
     * @param   string arg
     * @throws  peer.ProtocolException
     */
    public function write($type, $arg) {
      $length= strlen($arg)+ 8;
      $packet= pack('CCnnCc', $type, 1, $length, 0x0000, $this->pkt, 0).$arg;

      // DEBUG Console::$err->writeLine('W-> ', array(
      // DEBUG   'type'    => $type,
      // DEBUG   'status'  => 1,
      // DEBUG   'length'  => $length,
      // DEBUG   'spid'    => 0x0000,
      // DEBUG   'packet'  => $this->pkt,
      // DEBUG  'window'  => 0
      // DEBUG ));
      // DEBUG Console::$err->writeLine(self::dump($packet));
 
      $this->sock->write($packet);
      $this->pkt= $this->pkt+ 1 & 0xFF;
    }

    /**
     * Get 
     *
     * @param   string format
     * @param   int length
     * @return  string
     */
    public function get($format, $length) {
      return unpack($format, $this->read($length));
    }

    /**
     * Get a token (reads one byte)
     *
     * @return  string
     */
    public function getToken() {
      return $this->read(1);
    }

    /**
     * Get a byte
     *
     * @return  int
     */
    public function getByte() {
      $u= unpack('C', $this->read(1));
      return $u[1];
    }

    /**
     * Get a short (2 bytes)
     *
     * @return  int
     */
    public function getShort() {
      $u= unpack('v', $this->read(2));
      return $u[1];
    }

    /**
     * Get a long (4 bytes)
     *
     * @return  int
     */
    public function getLong() {
      $u= unpack('V', $this->read(4));
      return $u[1];
    }

    /**
     * Reads a string
     *
     * @param   int length
     * @return  string
     */
    public function getString($length) {
      if (0 === $length) return NULL;
      return iconv('ucs-2le', 'iso-8859-1//IGNORE', $this->read($length * 2));
    }
    
    /**
     * Begin reading a message
     *
     * @return  int message type
     */
    public function begin() {
      $this->header= array('status' => 0, 'length' => -1);
      $this->buffer= '';
      $this->read0(1);
      return $this->header['type'];
    }
    
    /**
     * Check for buffer underrun and read as many packets as necessary
     *
     * @param   int length
     * @return  int maximum length available
     * @throws  rdbms.tds.TdsProtocolException
     */
    protected function read0($length) {
      while (-1 === $length || strlen($this->buffer) < $length) {
        if (1 === $this->header['status']) return strlen($this->buffer);

        $bytes= $this->sock->readBinary(8);
        $this->header= unpack('Ctype/Cstatus/nlength/nspid/Cpacket/cwindow', $bytes);
        if (FALSE === $this->header) {
          $this->header['status']= 1;
          $e= new TdsProtocolException(
            'Expecting header, have unknown byte sequence '.addcslashes($bytes, "\0..\17\177..\377"),
            0,    // Number
            0,    // State
            0,    // Class
            NULL, // Server
            NULL, // Proc
            -1    // Line
          );
          xp::gc(__FILE__);
          throw $e;
        }
        
        // Console::$err->writeLine('R-> ', $this->header);
        $packet= $this->sock->readBinary($this->header['length'] - 8);
        // DEBUG Console::$err->writeLine(self::dump($packet));
        $this->buffer.= $packet;
      }

      return $length;
    }

    /**
     * Read a given number of bytes
     *
     * @param   int length
     * @return  string
     * @throws  rdbms.tds.TdsProtocolException
     */
    public function read($length) {
      $length= $this->read0($length);

      // Return chunk of specified length
      $chunk= substr($this->buffer, 0, $length);
      $this->buffer= substr($this->buffer, $length);
      return (string)$chunk;
    }
  }
?>