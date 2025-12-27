#!/bin/bash

# Load environment variables from .env file
if [ ! -f .env ]; then
    echo "Error: .env file not found"
    exit 1
fi

# Export variables from .env
export $(grep -v '^#' .env | xargs)

# Plugin directory name
PLUGIN_DIR="wp-sync-db"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Starting deployment to Pressable...${NC}"

# Check if SSH key exists
if [ ! -f "$TAG_SSH_PRIVATE_KEY" ]; then
    echo -e "${RED}Error: SSH private key not found at $TAG_SSH_PRIVATE_KEY${NC}"
    exit 1
fi

# Deployment mode: ssh or sftp (default: ssh)
DEPLOY_MODE=${1:-ssh}

# Extract plugin version from main file
PLUGIN_VERSION=$(grep -i "Version:" wp-sync-db.php | head -1 | awk '{print $3}')

echo -e "${GREEN}=== Pressable MultiSite Plugin Deployment ===${NC}"
echo -e "Plugin Version: ${YELLOW}${PLUGIN_VERSION}${NC}"
echo -e "Mode: ${YELLOW}${DEPLOY_MODE}${NC}"
echo ""

# Function to deploy via SSH/rsync
deploy_ssh() {
    echo -e "${GREEN}Deploying via SSH (rsync)...${NC}"

    # Check if SSH key exists
    if [ ! -f "$TAG_SSH_PRIVATE_KEY" ]; then
        echo -e "${RED}Error: SSH key not found at $TAG_SSH_PRIVATE_KEY${NC}"
        exit 1
    fi

    # Build SSH command with key
    SSH_CMD="ssh -i $TAG_SSH_PRIVATE_KEY -p ${TAG_SSH_PORT:-22}"

    # Execute rsync (use Homebrew rsync for protocol compatibility)
    echo -e "${YELLOW}Syncing files to $TAG_SSH_HOST...${NC}"
    /usr/local/bin/rsync -avz --delete --delete-excluded \
        --exclude='.git' \
        --exclude='.env' \
        --exclude='.env.bak' \
        --exclude='node_modules' \
        --exclude='.DS_Store' \
        --exclude='deploy.sh' \
        --exclude='deploy2.sh' \
        --exclude='*.zip' \
        --exclude='.claude' \
        --exclude='composer.lock' \
        --exclude='vendor' \
        --exclude='phpcs.xml.dist' \
        -e "$SSH_CMD" \
        ./ \
        "${TAG_SSH_USER}@${TAG_SSH_HOST}:${TAG_SSH_PATH}/"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Deployment successful via SSH${NC}"
    else
        echo -e "${RED}✗ Deployment failed${NC}"
        exit 1
    fi
}

# Function to deploy via SFTP
deploy_sftp() {
    echo -e "${GREEN}Deploying via SFTP...${NC}"

    # Create a temporary batch file for SFTP commands
    BATCH_FILE=$(mktemp)

    cat > $BATCH_FILE << EOF
cd $TAG_SFTP_PATH
put -r includes
put -r assets
put wp-sync-db.php
put uninstall.php
put composer.json
put README.md
put CLAUDE.md
quit
EOF

    echo -e "${YELLOW}Uploading files to $TAG_SFTP_HOST...${NC}"

    # Execute SFTP with batch file
    sshpass -p "$TAG_SFTP_PASSWORD" sftp -b $BATCH_FILE "${TAG_SFTP_USER}@${TAG_SFTP_HOST}"

    # Clean up
    rm -f $BATCH_FILE

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Deployment successful via SFTP${NC}"
    else
        echo -e "${RED}✗ Deployment failed${NC}"
        exit 1
    fi
}

# Main deployment logic
case $DEPLOY_MODE in
    ssh)
        deploy_ssh
        ;;
    sftp)
        deploy_sftp
        ;;
    *)
        echo -e "${RED}Invalid deployment mode: $DEPLOY_MODE${NC}"
        echo "Usage: ./deploy.sh [ssh|sftp]"
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}=== Deployment Complete ===${NC}"
if [ "$DEPLOY_MODE" = "ssh" ]; then
    echo -e "Plugin deployed to: ${YELLOW}${TAG_SSH_PATH}${NC}"
else
    echo -e "Plugin deployed to: ${YELLOW}${TAG_SFTP_PATH}${NC}"
fi
