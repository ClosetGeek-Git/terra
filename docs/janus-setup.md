# Janus Gateway Setup for Terra

This guide will help you set up Janus Gateway to work with Terra Admin Framework.

## Prerequisites

- Linux system (Ubuntu/Debian recommended)
- Root or sudo access
- Network access to required ports

## Installing Janus Gateway

### Option 1: From Source

```bash
# Install dependencies
sudo apt-get update
sudo apt-get install -y \
    libmicrohttpd-dev \
    libjansson-dev \
    libssl-dev \
    libsrtp2-dev \
    libsofia-sip-ua-dev \
    libglib2.0-dev \
    libopus-dev \
    libogg-dev \
    libcurl4-openssl-dev \
    liblua5.3-dev \
    libconfig-dev \
    pkg-config \
    gengetopt \
    libtool \
    automake \
    build-essential \
    wget \
    git

# Install libnice
cd /tmp
git clone https://gitlab.freedesktop.org/libnice/libnice
cd libnice
./autogen.sh
./configure --prefix=/usr
make && sudo make install

# Install libsrtp (if not available via package manager)
cd /tmp
wget https://github.com/cisco/libsrtp/archive/v2.4.2.tar.gz
tar xfv v2.4.2.tar.gz
cd libsrtp-2.4.2
./configure --prefix=/usr --enable-openssl
make shared_library && sudo make install

# Install Janus Gateway
cd /tmp
git clone https://github.com/meetecho/janus-gateway.git
cd janus-gateway
sh autogen.sh
./configure --prefix=/opt/janus --enable-post-processing
make
sudo make install
sudo make configs
```

### Option 2: Using Docker

```bash
docker pull meetecho/janus-gateway:latest
docker run -d --name janus \
  -p 7889:7889 \
  meetecho/janus-gateway:latest
```

## Configuring ZeroMQ Transport

### 1. Install ZeroMQ

```bash
sudo apt-get install -y libzmq3-dev
```

### 2. Rebuild Janus with ZeroMQ Support

If you built from source:

```bash
cd /tmp/janus-gateway
./configure --prefix=/opt/janus --enable-post-processing --enable-zeromq
make
sudo make install
```

### 3. Configure janus.transport.zmq.jcfg

Edit `/opt/janus/etc/janus/janus.transport.zmq.jcfg`:

```
general: {
    enabled = true                  # Enable ZeroMQ transport
    events = true                   # Enable event notifications
    json = "compact"                # JSON format
}

admin: {
    admin_enabled = true            # Enable admin API
    admin_base_path = "/admin"      # Admin API path
    bind = "tcp://*:7889"          # Bind address for admin API
}
```

### 4. Set Admin Secret

Edit `/opt/janus/etc/janus/janus.jcfg`:

```
general: {
    # ... other settings ...
    admin_secret = "janusoverlord"  # Set a strong secret in production
}
```

### 5. Enable Required Plugins

Edit plugin configuration files as needed:

**VideoRoom** (`/opt/janus/etc/janus/janus.plugin.videoroom.jcfg`):
```
general: {
    admin_key = "your_admin_key"    # Optional admin key
}
```

**Streaming** (`/opt/janus/etc/janus/janus.plugin.streaming.jcfg`):
```
general: {
    admin_key = "your_admin_key"    # Optional admin key
}
```

## Starting Janus Gateway

### From Source

```bash
# Start Janus
/opt/janus/bin/janus

# Or as a daemon
/opt/janus/bin/janus -b

# Or with logging
/opt/janus/bin/janus -o /var/log/janus.log
```

### With systemd

Create `/etc/systemd/system/janus.service`:

```ini
[Unit]
Description=Janus WebRTC Server
After=network.target

[Service]
Type=simple
ExecStart=/opt/janus/bin/janus -o /var/log/janus.log
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable janus
sudo systemctl start janus
```

### With Docker

```bash
docker run -d --name janus \
  -v /path/to/configs:/opt/janus/etc/janus \
  -p 7889:7889 \
  meetecho/janus-gateway:latest
```

## Verifying the Setup

### Check if Janus is Running

```bash
# Check process
ps aux | grep janus

# Check port
netstat -tlnp | grep 7889

# Or with lsof
lsof -i :7889
```

### Test ZeroMQ Connection

Using Terra's basic example:

```bash
cd /path/to/terra
php examples/basic_usage.php
```

### Check Logs

```bash
# If using systemd
sudo journalctl -u janus -f

# If logging to file
tail -f /var/log/janus.log
```

## Firewall Configuration

If you're using a firewall, open the required ports:

```bash
# Admin API port
sudo ufw allow 7889/tcp

# Add other ports as needed (HTTP, WebSocket, RTP, etc.)
sudo ufw allow 8088/tcp    # HTTP
sudo ufw allow 8188/tcp    # WebSocket
sudo ufw allow 10000:20000/udp  # RTP/RTCP
```

## Security Considerations

1. **Change the admin secret**: Never use the default secret in production
2. **Use strong secrets**: Generate random, strong secrets for admin_secret and admin_key
3. **Restrict access**: Use firewall rules to restrict admin API access to trusted IPs
4. **Use TLS**: Consider setting up TLS for production environments
5. **Monitor logs**: Regularly check logs for suspicious activity

## Troubleshooting

### Connection Refused

- Check if Janus is running
- Verify the port is correct (7889)
- Check firewall rules
- Verify ZeroMQ transport is enabled

### Authentication Failed

- Verify admin_secret matches in both Janus config and Terra config
- Check for typos in the secret

### Plugin Not Found

- Ensure the plugin is installed
- Check plugin configuration files
- Verify plugin is enabled in janus.jcfg

### No Response from Admin API

- Check Janus logs for errors
- Verify ZeroMQ transport is properly configured
- Test with telnet: `telnet localhost 7889`

## Next Steps

Once Janus is configured and running:

1. Review the [Terra examples](../examples/)
2. Read the [API documentation](api-reference.md)
3. Start building your application

## References

- [Janus Gateway Documentation](https://janus.conf.meetecho.com/docs/)
- [Janus Admin API](https://janus.conf.meetecho.com/docs/admin.html)
- [ZeroMQ Documentation](https://zeromq.org/documentation/)
