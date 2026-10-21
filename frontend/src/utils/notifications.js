// Forma real (Swagger): Notification { id, userId, message, read, createdAt }
export const isRead = (n) => Boolean(n.read)

export const notificationText = (n) => n.message ?? ''
