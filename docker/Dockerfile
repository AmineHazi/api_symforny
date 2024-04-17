# Use an official PHP runtime as a parent image
FROM php:7.4-cli

# Install dependencies for cURL and libxml
RUN apt-get update && apt-get install -y \
        curl \
        libxml2-dev \
    && docker-php-ext-install -j$(nproc) \
        dom

# Set the working directory in the container
WORKDIR /usr/src/myapp

# Copy the current directory contents into the container at /usr/src/myapp
COPY . /usr/src/myapp

# Run the script when the container launches
ENTRYPOINT ["php", "test.php"]
CMD ["http://example.com"] # Default URL if none is provided

