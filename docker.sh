docker run -d \
    -p 8082:80 \
    --name=lg \
    --restart=always \
    -v $PWD/htdocs:/var/www/html/ \
    rguaitanele/lg_hsdn
