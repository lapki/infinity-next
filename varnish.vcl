# Varnish config for nextchan/infinity-next.

# Marker to tell the VCL compiler that this VCL has been adapted to the
# new 4.0 format.
vcl 4.0;

# Default server, do not delete this.
backend default {
    .host = "127.0.0.1";
    .port = "83";
}

# Uncomment this if you have a separate media server.
# backend media {
#     .host = "127.0.0.1";
#     .port = "84";
# }

sub vcl_recv {
    # Uncomment this if you have a separate media server.
    # if (req.http.host ~ "media.nextchan.org") {
    #      set req.backend_hint = media;
    # } else {
    #      set req.backend_hint = default;
    # }
    
    # Send directly to the webserver if we are sending a file.
    if (req.http.Content-Type ~ "multipart/form-data") {
        return (pipe);
    }

    # handle encodings.
    if (req.http.Accept-Encoding) {
        if (req.url ~ "\.(jpg|jpeg|png|gif|webm|mp4|ogg|mp3|svg|swf|ico)$") {
            unset req.http.Accept-Encoding;
        } elsif (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            # Broken header.
            unset req.http.Accept-Encoding;
        }
    }

    # If this is a POST request pass it directly to the webserver.
    if (req.method == "POST") {
        return (pass);
    }

    # Do not cache these URLs.
    if (req.url ~ "(cp|spoiler|unspoiler|remove|edit|ban|history|delete|report)") {
        return (pass);
    }

    # If this is a static file remove the cookie header and cache it.
    if (req.url ~ "\.(js|css|jpg|jpeg|png|gif|webm|mp4|ogg|mp3|svg|swf|ico)$") {
        unset req.http.cookie;
        return (hash);
    }

    # Cache by default.
    return (hash);
}

sub vcl_backend_response {
    # Set default TTL to 5 minutes.
    set beresp.ttl = 5m;

    # Always process ESI. We need this for the top navigation.
    set beresp.do_esi  = true;

    # Different TTL for ESI.
    if (bereq.url ~ ".internal") {
        set beresp.ttl = 30s;
        # Do not cache the post form.
        if (bereq.url ~ "post-form") {
            set beresp.ttl = 0s;
        }
    }

    # cache json for 15 seconds.
    if (bereq.url ~ "\.json$") {
        set beresp.ttl = 15s;
    }
    
    # Cache images/videos shorter than audio in case an illegal picture gets cached.
    if ((bereq.method == "GET" && bereq.url ~ "\.(jpg|jpeg|png|gif|webm|mp4|svg|swf)" && bereq.url ~ "(?!cp)")) {
        unset beresp.http.Set-Cookie;
        set beresp.ttl = 15m;
    }
    if (bereq.method == "GET" && bereq.url ~ "\.(ogg|mp3)") {
        unset beresp.http.Set-Cookie;
        set beresp.ttl = 3h;
    }

    # Cache CSS/JS for five days.
    if (bereq.method == "GET" && bereq.url ~ "\.(css|js)") {
        unset beresp.http.Set-Cookie;
        set beresp.ttl = 5d;
    }

    # if (beresp.http.X-No-Session ~ "y") {
        # unset beresp.http.Set-Cookie;
    # }

    # Do not send the client info about the server.
    unset beresp.http.Server;
    unset beresp.http.X-Powered-By;

    return (deliver);
}

sub vcl_deliver {
    
    # Do not tell the client we are using Varnish.
    unset resp.http.Via;
    unset resp.http.X-Varnish;
}

sub vcl_pass {
    return (fetch);
}
