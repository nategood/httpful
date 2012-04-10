var http = require('http');

// Echo Server
// Responds with {requestMethod: "", requestHeaders:{}, requestBody:""}
http.createServer(function(req, res){
	var echo = "";
	var code = req.url == '/400' ? 400 : 200;
	var headers = {"Content-Type":"application/json"};
	
	// For the 301
	if (req.url === '/301') {
	    headers["Location"] = "http://localhost:8008/";
	    code = "301";
	}
	
    res.writeHead(code, headers);
    req.on('data', function(data){
        echo += data;
    });
    req.on('end', function(){
		res.write(
			JSON.stringify({
				requestMethod: 	req.method,
				requestHeaders: req.headers,
				requestBody: 	echo
			})
		);
		console.log(" --- Closing Connection From " + req.headers.host + " --- ");
        res.end();
    });
}).listen(8008);