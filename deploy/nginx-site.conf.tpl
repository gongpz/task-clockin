# 任务打卡系统 · Nginx 站点（install.sh 生成；__DOMAIN__ / __ROOT__ / __FPM__ 会被替换）
server {
    listen 80;
    server_name __DOMAIN__;
    root __ROOT__;
    index index.php;
    client_max_body_size 20m;

    # 程序目录、配置、部署脚本、运行日志、文档一律不对外
    location ~* ^/(app|deploy|storage/(logs|backups))(/|$) { return 404; }
    location ~* ^/storage/license                          { return 404; }
    location ~* /\.                                          { return 404; }
    location ~* \.(sql|gz|md|tpl|sh|log|bak.*)$              { return 404; }

    # 打卡上传的图片：只当静态文件，禁止执行任何脚本
    location ^~ /storage/uploads/ {
        location ~* \.(php|phtml|phar)$ { return 404; }
        expires 30d;
        access_log off;
    }

    # 所有页面和接口都交给 index.php 路由
    location / {
        try_files $uri /index.php?$query_string;
    }

    location = /index.php {
        fastcgi_pass __FPM__;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }
    # 除入口外的 .php 不允许直接访问
    location ~ \.php$ { return 404; }
}
