#!/bin/bash

# Janus Gateway Setup Script for Terra
# This script installs and configures Janus Gateway with multiple transport options

set -e

echo "========================================="
echo "Janus Gateway Setup for Terra"
echo "========================================="

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root or with sudo${NC}"
    exit 1
fi

# Check OS
if [ ! -f /etc/os-release ]; then
    echo -e "${RED}Cannot determine OS. This script is for Debian/Ubuntu systems.${NC}"
    exit 1
fi

source /etc/os-release

if [[ "$ID" != "ubuntu" && "$ID" != "debian" ]]; then
    echo -e "${YELLOW}Warning: This script is tested on Ubuntu/Debian. Your OS: $ID${NC}"
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

echo -e "${GREEN}Installing Janus Gateway...${NC}"

# Update package list
apt-get update

# Install Janus and development files
echo -e "${GREEN}Installing janus and janus-dev packages...${NC}"
apt-get install -y janus janus-dev

# Check if installation succeeded
if ! command -v janus &> /dev/null; then
    echo -e "${RED}Janus installation failed!${NC}"
    exit 1
fi

echo -e "${GREEN}Janus installed successfully!${NC}"
janus --version

# Create configuration directory if it doesn't exist
JANUS_CONFIG_DIR="/etc/janus"
mkdir -p "$JANUS_CONFIG_DIR"

# Backup existing configurations
if [ -f "$JANUS_CONFIG_DIR/janus.jcfg" ]; then
    echo -e "${YELLOW}Backing up existing janus.jcfg...${NC}"
    cp "$JANUS_CONFIG_DIR/janus.jcfg" "$JANUS_CONFIG_DIR/janus.jcfg.backup.$(date +%Y%m%d_%H%M%S)"
fi

# Configure admin secret in janus.jcfg
echo -e "${GREEN}Configuring Janus admin API...${NC}"
cat > "$JANUS_CONFIG_DIR/janus.jcfg" << 'EOF'
general: {
    configs_folder = "/etc/janus"
    plugins_folder = "/usr/lib/janus/plugins"
    transports_folder = "/usr/lib/janus/transports"
    events_folder = "/usr/lib/janus/events"
    log_to_stdout = false
    log_to_file = "/var/log/janus/janus.log"
    debug_level = 4
    admin_secret = "janusoverlord"
    api_secret = "janussecret"
}

nat: {
    stun_server = "stun.l.google.com"
    stun_port = 19302
}

media: {
    ipv6 = false
}
EOF

echo -e "${GREEN}Janus main configuration created.${NC}"

# Configure HTTP/REST API transport
echo -e "${GREEN}Configuring HTTP/REST API transport...${NC}"
cat > "$JANUS_CONFIG_DIR/janus.transport.http.jcfg" << 'EOF'
general: {
    json = "indented"
    base_path = "/janus"
    threads = "unlimited"
    http = true
    port = 8088
    https = false
    admin_base_path = "/admin"
}

admin: {
    admin_base_path = "/admin"
    admin_threads = "unlimited"
    admin_http = true
    admin_port = 7088
    admin_https = false
}

cors: {
    allow_origin = "*"
}
EOF

echo -e "${GREEN}HTTP/REST API transport configured.${NC}"

# Configure UnixSocket transport
echo -e "${GREEN}Configuring UnixSocket transport...${NC}"
mkdir -p /var/run/janus

cat > "$JANUS_CONFIG_DIR/janus.transport.pfunix.jcfg" << 'EOF'
general: {
    enabled = true
    json = "indented"
    type = "SOCK_SEQPACKET"
}

admin: {
    admin_enabled = true
    admin_path = "/var/run/janus/janus-admin.sock"
}
EOF

echo -e "${GREEN}UnixSocket transport configured.${NC}"

# Try to install janus-zeromq plugin
echo -e "${GREEN}Attempting to install janus-zeromq plugin...${NC}"

# Check if the package exists in any repository
if apt-cache search janus-zeromq | grep -q janus-zeromq; then
    apt-get install -y janus-zeromq || {
        echo -e "${YELLOW}Warning: janus-zeromq installation failed. Continuing without ZMQ support.${NC}"
        echo -e "${YELLOW}You can try to install @ClosetGeek-Git/janus-zeromq manually later.${NC}"
    }
else
    echo -e "${YELLOW}janus-zeromq package not found in repositories.${NC}"
    echo -e "${YELLOW}Attempting to install from alternative sources...${NC}"
    
    # Try to install ZMQ dependencies
    apt-get install -y libzmq3-dev || {
        echo -e "${YELLOW}Could not install ZMQ dependencies.${NC}"
    }
    
    # Check if @ClosetGeek-Git/janus-zeromq is available
    echo -e "${YELLOW}You may need to manually compile janus-zeromq from source.${NC}"
    echo -e "${YELLOW}Continuing without ZMQ transport...${NC}"
fi

# Configure ZMQ transport (if available)
if [ -f /usr/lib/janus/transports/libjanus_zmq.so ]; then
    echo -e "${GREEN}Configuring ZeroMQ transport...${NC}"
    
    cat > "$JANUS_CONFIG_DIR/janus.transport.zmq.jcfg" << 'EOF'
general: {
    enabled = true
    events = true
    json = "compact"
}

admin: {
    admin_enabled = true
    admin_base_path = "/admin"
    bind = "tcp://*:7889"
}
EOF
    
    echo -e "${GREEN}ZeroMQ transport configured.${NC}"
else
    echo -e "${YELLOW}ZeroMQ transport library not found. Skipping ZMQ configuration.${NC}"
fi

# Set proper permissions
chmod 644 "$JANUS_CONFIG_DIR"/*.jcfg
chown -R janus:janus /var/run/janus 2>/dev/null || true
chown -R janus:janus /var/log/janus 2>/dev/null || true

# Create systemd service if it doesn't exist
if [ ! -f /etc/systemd/system/janus.service ]; then
    echo -e "${GREEN}Creating systemd service...${NC}"
    cat > /etc/systemd/system/janus.service << 'EOF'
[Unit]
Description=Janus WebRTC Server
After=network.target

[Service]
Type=simple
User=janus
Group=janus
ExecStart=/usr/bin/janus -o
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
    
    systemctl daemon-reload
fi

# Enable and restart Janus
echo -e "${GREEN}Starting Janus Gateway...${NC}"
systemctl enable janus
systemctl restart janus

# Wait for Janus to start
sleep 3

# Check if Janus is running
if systemctl is-active --quiet janus; then
    echo -e "${GREEN}✓ Janus Gateway is running${NC}"
else
    echo -e "${RED}✗ Janus Gateway failed to start${NC}"
    echo -e "${YELLOW}Check logs: journalctl -u janus -n 50${NC}"
    exit 1
fi

echo ""
echo "========================================="
echo -e "${GREEN}Janus Gateway Setup Complete!${NC}"
echo "========================================="
echo ""
echo "Configuration summary:"
echo "  - Admin Secret: janusoverlord"
echo "  - API Secret: janussecret"
echo ""
echo "Available transports:"
echo "  - HTTP/REST API: http://localhost:7088/admin"
echo "  - UnixSocket: /var/run/janus/janus-admin.sock"

if [ -f /usr/lib/janus/transports/libjanus_zmq.so ]; then
    echo "  - ZeroMQ: tcp://localhost:7889"
else
    echo -e "  - ${YELLOW}ZeroMQ: Not available${NC}"
fi

echo ""
echo "Commands:"
echo "  - View logs: journalctl -u janus -f"
echo "  - Restart: systemctl restart janus"
echo "  - Stop: systemctl stop janus"
echo "  - Status: systemctl status janus"
echo ""
echo "Test with Terra:"
echo "  - php examples/basic_usage.php"
echo ""
