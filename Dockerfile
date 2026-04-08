FROM node:18-alpine
WORKDIR /app
COPY package*.json ./
RUN npm ci --omit=dev
COPY . .
RUN npx vite build
ENV NODE_ENV=production
EXPOSE 3000
CMD ["node", "server.js"]
