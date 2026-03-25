import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import ResearchAgent from './ResearchAgent.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <ResearchAgent />
  </StrictMode>,
)
