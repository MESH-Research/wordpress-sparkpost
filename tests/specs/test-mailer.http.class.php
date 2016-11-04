<?php
/**
* @package wp-sparkpost
*/
namespace WPSparkPost;
use \Nyholm\NSA;
use \Mockery;

class TestHttpMailer extends \WP_UnitTestCase {
  var $mailer;

  function setUp() {
    $this->mailer = new SparkPostHTTPMailer();
  }

  function test_mailSend_calls_sparkpost_send() {
    $stub = Mockery::mock($this->mailer);
    $stub->shouldReceive('sparkpost_send')->andReturn('woowoo');

    $this->assertTrue(NSA::invokeMethod($stub, 'mailSend', null, null) == 'woowoo');
  }

  function test_mailer_is_a_mailer_instance() {
    $this->assertTrue( $this->mailer instanceof \PHPMailer );
  }

  function test_get_sender_with_name() {
    $this->mailer->setFrom( 'me@hello.com', 'me' );
    $sender = array(
      'name' => 'me',
      'email' => 'me@hello.com'
    );

    $this->assertTrue(NSA::invokeMethod($this->mailer, 'get_sender') == $sender);
  }

  function test_get_sender_without_name() {
    $this->mailer->setFrom( 'me@hello.com', '' );
    $sender = array(
      'email' => 'me@hello.com'
    );

    $this->assertTrue(NSA::invokeMethod($this->mailer, 'get_sender') == $sender);
  }

  function test_get_request_headers() {
    $expected = array(
      'User-Agent' => 'wordpress-sparkpost/' . WPSP_PLUGIN_VERSION,
      'Content-Type' => 'application/json',
      'Authorization' => ''
    );
    $this->assertTrue(NSA::invokeMethod($this->mailer, 'get_request_headers') == $expected);

    NSA::setProperty($this->mailer, 'settings', array('password' => 'abcd1234'));
    $expected = array(
      'User-Agent' => 'wordpress-sparkpost/' . WPSP_PLUGIN_VERSION,
      'Content-Type' => 'application/json',
      'Authorization' => 'abcd1234'
    );
    $this->assertTrue(NSA::invokeMethod($this->mailer, 'get_request_headers') == $expected);
  }

  function test_get_request_headers_obfuscate_key() {
    NSA::setProperty($this->mailer, 'settings', array('password' => 'abcd1234'));
    $expected = array(
      'User-Agent' => 'wordpress-sparkpost/' . WPSP_PLUGIN_VERSION,
      'Content-Type' => 'application/json',
      'Authorization' => 'abcd'.str_repeat('*', 36)
    );
    $this->assertTrue(NSA::invokeMethod($this->mailer, 'get_request_headers', true) == $expected);
  }

  function test_get_headers() {
    $raw_headers = "Date: Wed, 26 Oct 2016 23:45:32 +0000
    To: undisclosed-recipients:;
    From: Root User <root@localhost>
    Subject: Hello
    Reply-To: replyto@mydomain.com
    Message-ID: <abcd@example.org>
    MIME-Version: 1.0
    Content-Type: text/plain; charset=iso-8859-1
    Content-Transfer-Encoding: 8bit";

    $expected = array(
      'Message-ID' => '<abcd@example.org>',
      'Date' => 'Wed, 26 Oct 2016 23:45:32 +0000'
    );
    $stub = Mockery::mock($this->mailer);
    $stub->shouldReceive('createHeader')->andReturn($raw_headers);
    $formatted_headers = NSA::invokeMethod($stub, 'get_headers');

    $this->assertTrue($formatted_headers == $expected);
  }


  function test_get_headers_should_include_cc_if_exists() {
    $raw_headers = "Date: Wed, 26 Oct 2016 23:45:32 +0000
    Reply-To: replyto@mydomain.com";

    $expected = array(
      'Date' => 'Wed, 26 Oct 2016 23:45:32 +0000',
      'CC' => 'hello@abc.com,Name <name@domain.com>'
    );
    $stub = Mockery::mock($this->mailer);
    $stub->shouldReceive('createHeader')->andReturn($raw_headers);
    $stub->addCc('hello@abc.com');
    $stub->addCc('name@domain.com', 'Name');

    $formatted_headers = NSA::invokeMethod($stub, 'get_headers');

    $this->assertTrue($formatted_headers == $expected);
  }

  function test_get_recipients() {
    $this->mailer->addAddress('to@abc.com');
    $this->mailer->addAddress('to1@abc.com', 'to1');
    $this->mailer->addCc('cc@abc.com');
    $this->mailer->addCc('cc1@abc.com', 'cc1');
    $this->mailer->addBcc('bcc@abc.com');
    $this->mailer->addBcc('bcc1@abc.com', 'bcc1');

    $header_to = implode(', ', [
      'to@abc.com',
      'to1 <to1@abc.com>',
    ]);

    $expected = [
      [
        'address' => [
          'email' => 'to@abc.com',
          'header_to' => $header_to
        ]
      ],
      [
        'address' => [
          'email' => 'to1@abc.com',
          'header_to' => $header_to
        ]
      ],
      [
        'address' => [
        'email' => 'bcc@abc.com',
        'header_to' => $header_to
        ]
      ],
      [
        'address' => [
        'email' => 'bcc1@abc.com',
        'header_to' => $header_to
        ]
      ],
      [
        'address' => [
        'email' => 'cc@abc.com',
        'header_to' => $header_to
        ]
      ],
      [
        'address' => [
        'email' => 'cc1@abc.com',
        'header_to' => $header_to
        ]
      ]
    ];

    $recipients = NSA::invokeMethod($this->mailer, 'get_recipients');
    $this->assertTrue($recipients == $expected);
  }

  function test_get_attachments() {
    $temp = tempnam('/tmp', 'php-wordpress-sparkpost');
    file_put_contents($temp, 'TEST');
    $this->mailer->addAttachment($temp);
    $attachments = NSA::invokeMethod($this->mailer, 'get_attachments');
    $this->assertTrue($attachments[0]['type'] === 'application/octet-stream');
    $this->assertTrue($attachments[0]['name'] === basename($temp));
    $this->assertTrue($attachments[0]['data'] === base64_encode('TEST'));
  }

  function test_isMail() {
    // test if isMail sets correct mailer
    $this->mailer->Mailer = 'abc';
    $this->assertTrue($this->mailer->Mailer === 'abc');
    $this->mailer->isMail();
    $this->assertTrue($this->mailer->Mailer === 'sparkpost');
  }
}
