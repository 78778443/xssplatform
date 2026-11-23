import { useQuery } from '@tanstack/react-query'
import { api } from './api'

export interface SessionUser {
  id: number
  userName: string
  adminLevel: number
  token: string
}

export interface SessionConfig {
  sitename: string
  sitedesc: string
  register: string
  urlroot: string
  urlrewrite: boolean
  expires: number
}

export interface SessionData {
  user: SessionUser | null
  csrf: string
  config: SessionConfig
  installed: boolean
}

export function useSession() {
  return useQuery({
    queryKey: ['session'],
    queryFn: () => api<SessionData>('session'),
    staleTime: 15_000,
  })
}
