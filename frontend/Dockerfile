# Solar Proposal MVP frontend — Vue 3 + Vite dev server.
#
# The MVP drives the full flow through the Vite dev server (which proxies /api
# to the Laravel container). A single-stage image is enough for the MVP; switch
# to a multi-stage build + static server once the app is deployed standalone.

FROM node:20-alpine

WORKDIR /app

# Install deps first so the node_modules layer is cached.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

EXPOSE 5173

# --host makes Vite bind to 0.0.0.0 so the port is reachable from the host.
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
