import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// When deploying to GitHub Pages the site lives at:
//   https://<user>.github.io/<repo>/
// Set VITE_BASE_PATH in your Actions workflow (see .github/workflows/deploy.yml).
// For local dev it defaults to '/' so nothing breaks.
export default defineConfig({
  plugins: [react()],
  base: process.env.VITE_BASE_PATH || '/',
})
