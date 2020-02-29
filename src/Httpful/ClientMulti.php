<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Curl\MultiCurl;
use Psr\Http\Message\RequestInterface;

class ClientMulti
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

    /**
     * @return void
     */
    public function start()
    {
        $this->curlMulti->start();
    }

    /**
     * @param string     $uri
     * @param array|null $params
     * @param string     $mime
     *
     * @return $this
     */
    public function add_delete(string $uri, array $params = null, string $mime = Mime::JSON)
    {
        $request = Request::delete($uri, $params, $mime);
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string $uri
     * @param string $file_path
     *
     * @return $this
     */
    public function add_download(string $uri, $file_path)
    {
        $request = Request::download($uri, $file_path);
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string      $uri
     * @param array|null  $params
     * @param string|null $mime
     *
     * @return $this
     */
    public function add_html(string $uri, array $params = null, $mime = Mime::HTML)
    {
        $request = Request::get($uri, $params, $mime)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string      $uri
     * @param array|null  $params
     * @param string|null $mime
     *
     * @return $this
     */
    public function add_get(string $uri, array $params = null, $mime = Mime::PLAIN)
    {
        $request = Request::get($uri, $params, $mime)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param array|null $params
     *
     * @return $this
     */
    public function add_get_dom(string $uri, array $params = null)
    {
        $request = Request::get($uri, $params, Mime::HTML)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param array|null $params
     *
     * @return $this
     */
    public function add_get_form(string $uri, array $params = null)
    {
        $request = Request::get($uri, $params, Mime::FORM)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param array|null $params
     *
     * @return $this
     */
    public function add_get_json(string $uri, array $params = null)
    {
        $request = Request::get($uri, $params, Mime::JSON)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param array|null $params
     *
     * @return $this
     */
    public function get_xml(string $uri, array $params = null)
    {
        $request = Request::get($uri, $params, Mime::XML)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string $uri
     *
     * @return $this
     */
    public function add_head(string $uri)
    {
        $request = Request::head($uri)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string $uri
     *
     * @return $this
     */
    public function add_options(string $uri)
    {
        $request = Request::options($uri);
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return $this
     */
    public function add_patch(string $uri, $payload = null, string $mime = Mime::PLAIN)
    {
        $request = Request::patch($uri, $payload, $mime);
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return $this
     */
    public function add_post(string $uri, $payload = null, string $mime = Mime::PLAIN)
    {
        $request = Request::post($uri, $payload, $mime)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     *
     * @return $this
     */
    public function add_post_dom(string $uri, $payload = null)
    {
        $request = Request::post($uri, $payload, Mime::HTML)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     *
     * @return $this
     */
    public function add_post_form(string $uri, $payload = null)
    {
        $request = Request::post($uri, $payload, Mime::FORM)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     *
     * @return $this
     */
    public function add_post_json(string $uri, $payload = null)
    {
        $request = Request::post($uri, $payload, Mime::JSON)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     *
     * @return $this
     */
    public function add_post_xml(string $uri, $payload = null)
    {
        $request = Request::post($uri, $payload, Mime::XML)->followRedirects();
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return $this
     */
    public function add_put(string $uri, $payload = null, string $mime = Mime::PLAIN)
    {
        $request = Request::put($uri, $payload, $mime);
        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }

    /**
     * @param Request|RequestInterface $request
     *
     * @return $this
     */
    public function add_request(RequestInterface $request)
    {
        if (!$request instanceof Request) {
            /** @noinspection PhpSillyAssignmentInspection - helper for PhpStorm */
            /** @var RequestInterface $request */
            $request = $request;

            /** @var Request $requestNew */
            $requestNew = Request::{$request->getMethod()}($request->getUri());
            $requestNew->withHeaders($request->getHeaders());
            $requestNew->withProtocolVersion($request->getProtocolVersion());
            $requestNew->withBody($request->getBody());
            $requestNew->withRequestTarget($request->getRequestTarget());

            $request = $requestNew;
        }

        $curl = $request->_curlPrep()->_curl();

        if ($curl) {
            $curl->request = $request;
            $this->curlMulti->addCurl($curl);
        }

        return $this;
    }
}
