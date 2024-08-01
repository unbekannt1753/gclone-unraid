#!/bin/bash

# Unraid GClone Plugin Installation Script

set -o errexit
set -o pipefail
set -o nounset

if [[ $(id -u) -ne 0 ]]; then
    echo "This script must be run as root" 
    exit 1
fi

# Set variables
INSTALL_DIR="/usr/local/bin"
PLUGIN_DIR="/boot/config/plugins/gclone-plugin"
CLDBIN="${INSTALL_DIR}/gclone"
TEMP_FILE="/tmp/gclone_temp"

# Determine OS architecture
OSARCH=$(uname -m)
case $OSARCH in 
    x86_64)
        BINTAG=Linux_x86_64
        ;;
    i*86)
        BINTAG=Linux_i386
        ;;
    aarch64)
        BINTAG=Linux_arm64
        ;;
    arm*)
        BINTAG=Linux_armv6
        ;;
    *)
        echo "Unsupported OSARCH: $OSARCH"
        exit 1
        ;;
esac

# Create plugin directory if it doesn't exist
mkdir -p "$PLUGIN_DIR"

# Download and install gclone
echo "Downloading gclone..."
wget -qO- https://api.github.com/repos/donwa/gclone/releases/latest \
| grep browser_download_url | grep "$BINTAG" | cut -d '"' -f 4 \
| wget --no-verbose -i- -O- | gzip -d -c > "$TEMP_FILE"

# Move the temporary file to the final location
mv "$TEMP_FILE" "$CLDBIN"

# Make gclone executable
chmod +x "$CLDBIN"

# Create a symlink to ensure gclone is in PATH
ln -sf "$CLDBIN" /usr/bin/gclone

# Create a startup script to ensure gclone persists after reboot
cat << EOF > "$PLUGIN_DIR/gclone_startup.sh"
#!/bin/bash
# This script runs at Unraid startup to ensure gclone is installed and accessible

if [ ! -f "$CLDBIN" ]; then
    echo "gclone not found, reinstalling..."
    /bin/bash "$PLUGIN_DIR/install.sh"
fi

# Ensure symlink exists
ln -sf "$CLDBIN" /usr/bin/gclone
EOF

chmod +x "$PLUGIN_DIR/gclone_startup.sh"

# Add startup script to Unraid's user scripts
if [ ! -f "/boot/config/plugins/user.scripts/scripts/gclone_startup" ]; then
    mkdir -p "/boot/config/plugins/user.scripts/scripts/gclone_startup"
    echo '#!/bin/bash' > "/boot/config/plugins/user.scripts/scripts/gclone_startup/script"
    echo "$PLUGIN_DIR/gclone_startup.sh" >> "/boot/config/plugins/user.scripts/scripts/gclone_startup/script"
    chmod +x "/boot/config/plugins/user.scripts/scripts/gclone_startup/script"
fi

echo "GClone installation complete!"

# Test gclone installation
if gclone version; then
    echo "GClone installed successfully!"
else
    echo "Error: GClone installation failed. Please check the logs and try again."
    exit 1
fi