/** PM2 production config for the Next.js storefront (apps/web). */
module.exports = {
  apps: [
    {
      name: "china-order-tz",
      cwd: "./apps/web",
      script: "npm",
      args: "run start",
      interpreter: "none",
      env: {
        NODE_ENV: "production",
        PORT: "3000",
      },
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: "1G",
    },
  ],
};
