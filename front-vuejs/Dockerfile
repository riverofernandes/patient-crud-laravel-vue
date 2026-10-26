# Production image: static build served by nginx.
# The dev stack (docker-compose `web` service) does NOT use this file: it runs the
# Vite dev server straight from the node image with the source bind-mounted.
#
#   docker build --build-arg VITE_API_URL=https://api.example.com/api -t patients-web:prod .
#   docker run --rm -p 8080:80 patients-web:prod

# ---- build ----
FROM node:22-alpine AS build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

# Vite inlines VITE_* at build time, so the API URL is baked into the bundle.
# It is resolved by the browser: use the public URL of the API, not a container name.
ARG VITE_API_URL
RUN test -n "$VITE_API_URL" || (echo "Build arg VITE_API_URL is required (e.g. https://api.example.com/api)" >&2 && exit 1)
ENV VITE_API_URL=$VITE_API_URL
RUN npm run build

# ---- runtime ----
FROM nginx:1.31-alpine AS runtime

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/security-headers.conf /etc/nginx/snippets/security-headers.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD wget -q --spider http://127.0.0.1/healthz || exit 1
