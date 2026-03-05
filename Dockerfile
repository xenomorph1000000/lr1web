FROM php:8.3-apache

# Установка зависимостей 
RUN apt-get update && apt-get install -y \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql

# Копируем код приложения
COPY ./src /var/www/html

# Настраиваем Apache
RUN a2enmod rewrite

# Устанавливаем рабочую директорию
WORKDIR /var/www/html

# Экспонируем порт
EXPOSE 80

# Команда запуска
CMD ["apache2-foreground"]