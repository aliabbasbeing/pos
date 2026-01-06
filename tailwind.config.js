/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./includes/*.php",
  ],
  theme: {
    extend: {
      colors: {
        primary: '#1E40AF',
        secondary: '#0EA5E9',
        success: '#10B981',
        warning: '#F59E0B',
        danger: '#EF4444',
        lightbg: '#F0F9FF',
        dark: '#1E3A8A',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
        heading: ['Poppins', 'ui-sans-serif', 'system-ui']
      }
    }
  },
  plugins: [],
}
