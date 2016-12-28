Date: 2012-02-17
Tags: Linux, Domácí Server

# Automatické domény a subdomény s nginxem


``` config
server {
    listen          127.0.0.1:80;
    server_name     ~^(:?(?<second>.+)\.)?(?<domain>[^.]+\.[^.]+)$;
    index           index.php index.html;

    set             $try_dir $domain;
    if (-d /var/www/hosts/$second.$domain) {
        set     $try_dir $second.$domain;
    }
    root            /var/www/hosts/$try_dir;

    location / {
        try_files       $uri $uri/ /index.php;
    }

    keepalive_timeout  0;
    send_timeout    9999999;
    fastcgi_read_timeout    999999;
    client_max_body_size    200M;

    location ~ \.php$ {
        # pass the PHP scripts to FastCGI server listening on 127.0.0.1:9000
        include         fastcgi_params;
        fastcgi_param   SERVER_NAME     $try_dir;
        fastcgi_pass    127.0.0.1:9000;
        fastcgi_index   index.php;

        fastcgi_split_path_info ^((?U).+\.php)(/?.+)$;
        fastcgi_param   PATH_INFO $fastcgi_path_info;
        fastcgi_param   PATH_TRANSLATED $document_root$fastcgi_path_info;

        fastcgi_param   SCRIPT_FILENAME $document_root$fastcgi_script_name;

        try_files $uri =404;
    }

    #location ~ \.(js|ico|gif|jpg|png|css|rar|zip|tar\.gz)$ { }

    location ~ /\.(ht|gitignore) {
        # deny access to .htaccess files,
        # if Apache's document root concurs with nginx's one
        deny all;
    }

    location ~ \.(neon|ini|log|yml)$ {
        # deny access to configuration files
        deny all;
    }

    location = /robots.txt  { access_log off; log_not_found off; }
    location = /humans.txt  { access_log off; log_not_found off; }
    location = /favicon.ico { access_log off; log_not_found off; }
}
```


Zbytek je [zde](https://gist.github.com/1853026)
