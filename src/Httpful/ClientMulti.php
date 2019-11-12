<?php

declare(strict_types=1);

namespace Httpful;

use Curl\MultiCurl;
use Psr\Http\Message\RequestInterface;

final class ClientMulti
{
    /**
     * @var MultiCurl
     */
    public $curlMulti;

    /**
     * @param callable|null $onSuccessCallback
     * @param callable|null $onCompleteCallback
     */
    public function __construct($onSuccessCallback = null, $onCompleteCallback = null)
    {
        $this->curlMulti = (new Request())
            ->initMulti($onSuccessCallback, $onCompleteCallback);
    }

    public function start()
    {
        $this->curlMulti->start();
    }

    /**
     * @param string $uri
     * @param string $mime
     */
    public function add_delete(string $uri, string $mime = Mime::JSON)
    {
        $request = Request::delete($uri, $mime);
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string $uri
     * @param string $file_path
     */
    public function add_download(string $uri, $file_path)
    {
        $request = Request::download($uri, $file_path);
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string      $uri
     * @param string|null $mime
     */
    public function add_get(string $uri, $mime = Mime::PLAIN)
    {
        $request = Request::get($uri, $mime)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string $uri
     */
    public function add_get_dom(string $uri)
    {
        $request = Request::get($uri, Mime::HTML)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string $uri
     */
    public function add_get_form(string $uri)
    {
        $request = Request::get($uri, Mime::FORM)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string $uri
     */
    public function add_get_json(string $uri)
    {
        $request = Request::get($uri, Mime::JSON)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string $uri
     */
    public function get_xml(string $uri)
    {
        $request = Request::get($uri, Mime::XML)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string $uri
     */
    public function add_head(string $uri)
    {
        $request = Request::head($uri)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string $uri
     */
    public function add_options(string $uri)
    {
        $request = Request::options($uri);
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     */
    public function add_patch(string $uri, $payload = null, string $mime = Mime::PLAIN)
    {
        $request = Request::patch($uri, $payload, $mime);
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     */
    public function add_post(string $uri, $payload = null, string $mime = Mime::PLAIN)
    {
        $request = Request::post($uri, $payload, $mime)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     */
    public function add_post_dom(string $uri, $payload = null)
    {
        $request = Request::post($uri, $payload, Mime::HTML)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     */
    public function add_post_form(string $uri, $payload = null)
    {
        $request = Request::post($uri, $payload, Mime::FORM)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     */
    public function add_post_json(string $uri, $payload = null)
    {
        $request = Request::post($uri, $payload, Mime::JSON)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     */
    public function add_post_xml(string $uri, $payload = null)
    {
        $request = Request::post($uri, $payload, Mime::XML)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     */
    public function add_put(string $uri, $payload = null, string $mime = Mime::PLAIN)
    {
        $request = Request::put($uri, $payload, $mime);
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }

    /**
     * @param Request|RequestInterface $request
     */
    public function add_request(RequestInterface $request)
    {
        if (!$request instanceof Request) {
            $request = Request::{$request->getMethod()}($request->getUri());
        }

        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->curlMulti->addCurl($curl);
        }
    }
}
