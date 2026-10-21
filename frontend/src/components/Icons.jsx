// Íconos de línea simples (stroke = currentColor), siempre decorativos.
function Icon({ size = 20, width = 2, children }) {
  return (
    <svg
      aria-hidden="true"
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={width}
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      {children}
    </svg>
  )
}

export const IconSearch = (p) => (
  <Icon {...p}>
    <circle cx="11" cy="11" r="6.5" />
    <path d="M16 16l4.5 4.5" />
  </Icon>
)

export const IconBell = (p) => (
  <Icon {...p}>
    <path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z" />
    <path d="M10 20.5a2 2 0 0 0 4 0" />
  </Icon>
)

export const IconCart = (p) => (
  <Icon {...p}>
    <path d="M3 4h2l2.4 11h10.2L20 7H6.2" />
    <circle cx="9" cy="19" r="1.4" />
    <circle cx="17" cy="19" r="1.4" />
  </Icon>
)

export const IconPlus = (p) => (
  <Icon width={2.4} {...p}>
    <path d="M12 5v14M5 12h14" />
  </Icon>
)

export const IconBack = (p) => (
  <Icon width={2.2} {...p}>
    <path d="M15 5l-7 7 7 7" />
  </Icon>
)

export const IconRefresh = (p) => (
  <Icon {...p}>
    <path d="M20 11a8 8 0 1 0-2.3 5.7" />
    <path d="M20 4v7h-7" />
  </Icon>
)

export const IconCheck = (p) => (
  <Icon width={2.4} {...p}>
    <path d="M5 12l5 5 9-10" />
  </Icon>
)

export const IconBox = (p) => (
  <Icon width={1.4} {...p}>
    <path d="M4 8l8-4 8 4v8l-8 4-8-4z" />
    <path d="M4 8l8 4 8-4" />
    <path d="M12 12v8" />
  </Icon>
)

export const IconHeadphones = (p) => (
  <Icon width={1} {...p}>
    <path d="M4 14v-2a8 8 0 0 1 16 0v2M4 14h3v5H4zM17 14h3v5h-3z" />
  </Icon>
)

export const IconKeyboard = (p) => (
  <Icon width={1.2} {...p}>
    <path d="M3 7h18v10H3zM7 11h.01M11 11h.01M15 11h.01M8 14h8" />
  </Icon>
)

export const IconLamp = (p) => (
  <Icon width={1.2} {...p}>
    <path d="M9 3h6l3 8H6zM12 11v8M8 21h8" />
  </Icon>
)
