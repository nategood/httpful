var http = require('http');

// Echo Server
// Responds with {requestMethod: "", requestHeaders:{}, requestBody:""}
http.createServer(function(req, res){
	var echo = "";

    res.writeHead(200);
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