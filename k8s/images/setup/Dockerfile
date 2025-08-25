FROM alpine:latest

# Install required packages
RUN apk add --no-cache curl unzip bash tar gzip

# Copy the setup script
COPY setup.sh /setup.sh
RUN chmod +x /setup.sh

# Set working directory
WORKDIR /workdir

# Default command
CMD ["/setup.sh"]