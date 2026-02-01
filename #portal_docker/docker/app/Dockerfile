# Używamy PHP-FPM na Debianie Bullseye (stabilny na Synology)
FROM php:8.2-fpm-bullseye

# Instalacja zależności (Grafika + Python)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    python3 \
    python3-pip \
    && rm -rf /var/lib/apt/lists/*

# Konfiguracja PHP (GD + MySQL)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql

# Instalacja biblioteki Pillow dla Pythona
RUN pip3 install Pillow

# PHP-FPM domyślnie nasłuchuje na porcie 9000
EXPOSE 9000
CMD ["php-fpm"]