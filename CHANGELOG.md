# Changelog

## 2.4.7 (2021-12-08)

- update "portable-utf8"

## 2.4.6 (2021-10-15)

- fix file upload

## 2.4.5 (2021-09-14)

- "XmlMimeHandler" -> show the borken xml

## 2.4.4 (2021-09-09)

- fixes for phpdoc only

## 2.4.3 (2021-04-07)

- fix for old PHP versions
- use Github Actions

## 2.4.2 (2020-11-18)

- update vendor stuff + fix tests

## 2.4.1 (2020-05-04)

- "Client->download()" -> added timeout parameter
- "HtmlMimeHandler" -> fix non UTF-8 string input
- "Response" -> fix Header-Parsing for empty responses

## 2.4.0 (2020-03-06)

- add "Request->withPort(int $port)"
- fix "Request Body Not Preserved" #7

## 2.3.2 (2020-02-29)

- "ClientMulti" -> add "add_html()"

## 2.3.1 (2020-02-29)

- merge upstream fixes from https://github.com/php-curl-class/php-curl-class/

## 2.3.0 (2020-01-28) 

- optimize "RequestInterface"-integration

## 2.2.0 (2020-01-28) 

- add "ClientPromise" (\Http\Client\HttpAsyncClient)

## 2.1.0 (2019-12-19)

- return $this for many methods from "Curl" & "MultiCurl"
- optimize the speed of "MultiCurl"
- use phpstan (0.12) + add more phpdocs

## 2.0.0 (2019-11-15)

- add $params for "GET" / "DELETE" requests
- free some more memory
- more helpfully exception messages
- fixes callbacks for "ClientMulti"

## 1.0.0 (2019-11-13)

 - fix all bugs reported by phpstan
 - clean-up dependencies
 - fix async support for POST data

## 0.10.0 (2019-11-12)

 - add support for async requests via CurlMulti

## 0.9.0 (2019-07-16)

 - add new header functions + many tests

## 0.8.0 (2019-07-06)

 - fix implementation of PSR standards + many tests

## 0.7.1 (2019-05-01)

 - fix "addHeaders()"

## 0.7.0 (2019-04-30)

 - fix return types of "Handlers"
 - add more helper functions for "Client" (with auto-completion via phpdoc)

## 0.6.0 (2019-04-30)

 - make more properties private && classes final v2
 - fix array usage with "Stream"
 - move "Request->init" into the "__constructor"
 - rename some internal classes + methods

## 0.5.0 (2019-04-29)

 - FEATURE Add "PSR-3" logging
 - FEATURE Add "PSR-18" HTTP Client - "\Httpful\Client"
 - FEATURE Add "PSR-7" - RequestInterface && ResponseInterface
 - fix issues reported by phpstan (level 7)
 - make properties private && classes final v1

## 0.4.x

 - update vendor
 - fix return types

## 0.3.x

 - drop support for < PHP7
 - use return types

## 0.2.x

 - "Add convenience methods for appending parameters to query string." [PR #65](https://github.com/nategood/httpful/pull/65)
 - "Give more information to the Exception object to enable better error handling" [PR #117](https://github.com/nategood/httpful/pull/117)
 - "Solves issue #170: HTTP Header parsing is inconsistent" [PR #182](https://github.com/nategood/httpful/pull/182)
 - "added support for http_proxy environment variable" [PR #183](https://github.com/nategood/httpful/pull/183)
 - "Fix for frameworks that use object proxies" + fixes phpdoc [PR #205](https://github.com/nategood/httpful/pull/205)
 - "ConnectionErrorException cURLError" [PR #207](https://github.com/nategood/httpful/pull/208)
 - "Added explicit support for expectsXXX" [PR #210](https://github.com/nategood/httpful/pull/210)
 - "Add connection timeout" [PR #215](https://github.com/nategood/httpful/pull/215)
 - use "portable-utf8" [voku](https://github.com/voku/httpful/commit/3b4b36bd65bdecd0dafaa7ace336ac9f629a0e5a)
 - fixed code-style / added php-docs / added "alias"-methods ... [voku](https://github.com/voku/httpful/commit/3b82723609d5decc6521b94d336f090bc9d764e3)
 
## 0.2.20

 - MINOR Move Response building logic into separate function [PR #193](https://github.com/nategood/httpful/pull/193)

## 0.2.19

 - FEATURE Before send hook [PR #164](https://github.com/nategood/httpful/pull/164)
 - MINOR More descriptive connection exceptions [PR #166](https://github.com/nategood/httpful/pull/166)

## 0.2.18

 - FIX [PR #149](https://github.com/nategood/httpful/pull/149)
 - FIX [PR #150](https://github.com/nategood/httpful/pull/150)
 - FIX [PR #156](https://github.com/nategood/httpful/pull/156)

## 0.2.17

 - FEATURE [PR #144](https://github.com/nategood/httpful/pull/144) Adds additional parameter to the Response class to specify additional meta data about the request/response (e.g. number of redirect).

## 0.2.16

 - FEATURE Added support for whenError to define a custom callback to be fired upon error. Useful for logging or overriding the default error_log behavior.

## 0.2.15

 - FEATURE [I #131](https://github.com/nategood/httpful/pull/131) Support for SOCKS proxy

## 0.2.14

 - FEATURE [I #138](https://github.com/nategood/httpful/pull/138) Added alternative option for XML request construction. In the next major release this will likely supplant the older version.

## 0.2.13

 - REFACTOR [I #121](https://github.com/nategood/httpful/pull/121) Throw more descriptive exception on curl errors
 - REFACTOR [I #122](https://github.com/nategood/httpful/issues/122) Better proxy scrubbing in Request
 - REFACTOR [I #119](https://github.com/nategood/httpful/issues/119) Better document the mimeType param on Request::body
 - Misc code and test cleanup

## 0.2.12

 - REFACTOR [I #123](https://github.com/nategood/httpful/pull/123) Support new curl file upload method
 - FEATURE [I #118](https://github.com/nategood/httpful/pull/118) 5.4 HTTP Test Server
 - FIX [I #109](https://github.com/nategood/httpful/pull/109) Typo
 - FIX [I #103](https://github.com/nategood/httpful/pull/103)  Handle also CURLOPT_SSL_VERIFYHOST for strictSsl mode

## 0.2.11

 - FIX [I #99](https://github.com/nategood/httpful/pull/99) Prevent hanging on HEAD requests

## 0.2.10

 - FIX [I #93](https://github.com/nategood/httpful/pull/86) Fixes edge case where content-length would be set incorrectly

## 0.2.9

 - FEATURE [I #89](https://github.com/nategood/httpful/pull/89) multipart/form-data support (a.k.a. file uploads)! Thanks @dtelaroli!

## 0.2.8

 - FIX Notice fix for Pull Request 86

## 0.2.7

 - FIX [I #86](https://github.com/nategood/httpful/pull/86) Remove Connection Established header when using a proxy

## 0.2.6

 - FIX [I #85](https://github.com/nategood/httpful/issues/85) Empty Content Length issue resolved

## 0.2.5

 - FEATURE [I #80](https://github.com/nategood/httpful/issues/80) [I #81](https://github.com/nategood/httpful/issues/81) Proxy support added with `useProxy` method.

## 0.2.4

 - FEATURE [I #77](https://github.com/nategood/httpful/issues/77) Convenience method for setting a timeout (seconds) `$req->timeoutIn(10);`
 - FIX [I #75](https://github.com/nategood/httpful/issues/75) [I #78](https://github.com/nategood/httpful/issues/78) Bug with checking if digest auth is being used.

## 0.2.3

 - FIX Overriding default Mime Handlers
 - FIX [PR #73](https://github.com/nategood/httpful/pull/73) Parsing http status codes

## 0.2.2

 - FEATURE Add support for parsing JSON responses as associative arrays instead of objects
 - FEATURE Better support for setting constructor arguments on Mime Handlers

## 0.2.1

 - FEATURE [PR #72](https://github.com/nategood/httpful/pull/72) Allow support for custom Accept header

## 0.2.0

 - REFACTOR [PR #49](https://github.com/nategood/httpful/pull/49) Broke headers out into their own class
 - REFACTOR [PR #54](https://github.com/nategood/httpful/pull/54) Added more specific Exceptions
 - FIX [PR #58](https://github.com/nategood/httpful/pull/58) Fixes throwing an error on an empty xml response
 - FEATURE [PR #57](https://github.com/nategood/httpful/pull/57) Adds support for digest authentication

## 0.1.6

 - Ability to set the number of max redirects via overloading `followRedirects(int max_redirects)`
 - Standards Compliant fix to `Accepts` header
 - Bug fix for bootstrap process when installed via Composer

## 0.1.5

 - Use `DIRECTORY_SEPARATOR` constant [PR #33](https://github.com/nategood/httpful/pull/32)
 - [PR #35](https://github.com/nategood/httpful/pull/35)
 - Added the raw\_headers property reference to response.
 - Compose request header and added raw\_header to Request object.
 - Fixed response has errors and added more comments for clarity.
 - Fixed header parsing to allow the minimum (status line only) and also cater for the actual CRLF ended headers as per RFC2616.
 - Added the perfect test Accept: header for all Acceptable scenarios see  @b78e9e82cd9614fbe137c01bde9439c4e16ca323 for details.
 - Added default User-Agent header
  - `User-Agent: Httpful/0.1.5` + curl version + server software + PHP version
 - To bypass this "default" operation simply add a User-Agent to the request headers even a blank User-Agent is sufficient and more than simple enough to produce me thinks.
 - Completed test units for additions.
 - Added phpunit coverage reporting and helped phpunit auto locate the tests a bit easier.

## 0.1.4

 - Add support for CSV Handling [PR #32](https://github.com/nategood/httpful/pull/32)

## 0.1.3

 - Handle empty responses in JsonParser and XmlParser

## 0.1.2

 - Added support for setting XMLHandler configuration options
 - Added examples for overriding XmlHandler and registering a custom parser
 - Removed the httpful.php download (deprecated in favor of httpful.phar)

## 0.1.1

 - Bug fix serialization default case and phpunit tests

## 0.1.0

 - Added Support for Registering Mime Handlers
 - Created AbstractMimeHandler type that all Mime Handlers must extend
 - Pulled out the parsing/serializing logic from the Request/Response classes into their own MimeHandler classes
 - Added ability to register new mime handlers for mime types

