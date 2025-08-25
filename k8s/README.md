# Kubernetes Migration Guide

This guide converts your Docker Compose LEMP stack to Kubernetes with local development and production deployment support.

## 🏗️ Architecture Overview

**Services:**
- **MySQL 9**: Database with initialization scripts
- **PHP Tribe**: Your custom application with local code mounting
- **PHP Junction**: Downloaded application from GitHub  
- **Nginx Tribe**: Web server on localhost:1212
- **Nginx Junction**: Web server on localhost:4488
- **Setup Init Container**: Downloads phpMyAdmin and Junction

## 📁 Directory Structure

```
k8s/
├── common/              # Shared resources (ConfigMaps, Secrets)
│   ├── namespace.yaml
│   ├── mysql-init-configmap.yaml
│   ├── app-config.yaml
│   └── nginx-configs.yaml
├── local/               # Local development templates
│   ├── mysql.yaml
│   ├── php-tribe.yaml
│   ├── php-junction.yaml
│   ├── nginx-tribe.yaml
│   └── nginx-junction.yaml
├── local-generated/     # Generated local manifests (created by setup)
├── production/          # Production manifests (to be created)
├── images/              # Custom Docker images
│   ├── setup/
│   ├── tribe/
│   └── junction/
├── setup-local.sh       # Local setup script
├── build-images.sh      # Image build script
└── README.md           # This file
```

## 🚀 Quick Start (Local Development)

### Step 1: Setup Local Environment
```bash
# Make scripts executable
chmod +x k8s/setup-local.sh k8s/build-images.sh

# Configure local paths and Docker Hub username
./k8s/setup-local.sh
```

### Step 2: Build Custom Images
```bash
# Build images locally
./k8s/build-images-local.sh

# Or build and push to Docker Hub
./k8s/build-images-local.sh push
```

### Step 3: Deploy to Kubernetes
```bash
# Apply all manifests
kubectl apply -f k8s/common/
kubectl apply -f k8s/local-generated/

# Check deployment status
kubectl get pods -n tribe-junction
kubectl get services -n tribe-junction
```

### Step 4: Access Applications
- **Tribe**: http://localhost:1212
- **Junction**: http://localhost:4488  
- **phpMyAdmin**: http://localhost:1212/phpmyadmin

## 🔧 Local Development Workflow

Your current development workflow remains unchanged:
- Edit files in `./applications/tribe/` - changes appear immediately
- Logs are written to `./logs/`
- Uploads are stored in `./uploads/`
- Junction code is automatically downloaded by init container

## 📊 Port Mapping

| Service | Docker Compose | Kubernetes (NodePort) |
|---------|----------------|----------------------|
| Tribe | localhost:1212 | localhost:1212 |
| Junction | localhost:4488 | localhost:4488 |
| MySQL | localhost:3306 | Internal only |

## 🐳 Custom Images

The setup builds three custom images:
1. **Setup Container**: Alpine + curl/unzip for downloading dependencies
2. **PHP Tribe**: PHP 8.4-FPM with extensions for Tribe application  
3. **PHP Junction**: PHP 8.4-FPM with extensions for Junction application

## 🔍 Troubleshooting

### Check Pod Status
```bash
kubectl get pods -n tribe-junction
kubectl describe pod <pod-name> -n tribe-junction
kubectl logs <pod-name> -n tribe-junction
```

### Check Services
```bash
kubectl get svc -n tribe-junction
```

### Access Pod Shell
```bash
kubectl exec -it <pod-name> -n tribe-junction -- /bin/bash
```

### Reset Everything
```bash
kubectl delete namespace tribe-junction
```

## 🌐 Production Deployment (DigitalOcean)

Production manifests will use:
- DigitalOcean Block Storage for persistent volumes
- LoadBalancer services instead of NodePort
- Images from Docker Hub registry
- Production environment variables

*Note: Production manifests to be created in next phase*

## 📝 Configuration Notes

- **Environment Variables**: Stored in ConfigMaps and Secrets
- **Database**: MySQL 9 with automatic table creation
- **File Permissions**: Containers run as user `www` (uid:1000)
- **Shared Storage**: Init containers populate shared volumes
- **Health Checks**: Liveness and readiness probes configured

## 🔄 Migration Benefits

✅ **Container Orchestration**: Auto-restart, scaling, health checks  
✅ **Environment Parity**: Same images for local and production  
✅ **Configuration Management**: Centralized config via ConfigMaps/Secrets  
✅ **Service Discovery**: Internal DNS resolution between services  
✅ **Resource Management**: CPU/memory limits and requests  
✅ **Rolling Updates**: Zero-downtime deployments  
✅ **Observability**: Built-in logging and monitoring integration