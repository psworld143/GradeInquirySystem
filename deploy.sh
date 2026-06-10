#!/bin/bash

echo "Starting deployment process..."

# Remove existing vendor directory and composer.lock
echo "Cleaning up old files..."
rm -rf vendor/
rm -f composer.lock

# Install dependencies with PHP 7.3 compatibility
echo "Installing dependencies..."
composer install --no-dev --ignore-platform-reqs --no-interaction

# Create necessary directories if they don't exist
echo "Setting up directories..."
mkdir -p uploads
chmod 777 uploads

# Set proper permissions
echo "Setting file permissions..."
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

echo "Deployment package prepared successfully!"

# Make the deploy script executable
chmod +x deploy.sh

# Run the deployment script
./deploy.sh 