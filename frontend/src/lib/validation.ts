import { z } from 'zod'

export const usernameSchema = z
  .string()
  .regex(/^[\w一-龥]{2,20}$/u, '4-20个字符(字母、汉字、数字、下划线)')

export const emailSchema = z
  .string()
  .regex(/^(\w+\.)*?\w+@(\w+\.)+\w+$/, '邮箱格式不正确')

export const passwordSchema = z.string().regex(/^.{6,20}$/s, '6-20个字符')

export const loginSchema = z.object({
  user: z.string().min(1, '用户/邮箱不能为空'),
  pwd: z.string().min(1, '密码不能为空'),
  auto: z.boolean(),
})

export const registerSchema = z
  .object({
    key: z.string(),
    user: usernameSchema,
    email: emailSchema,
    pwd: passwordSchema,
    pwd2: z.string(),
  })
  .refine((value) => value.pwd === value.pwd2, {
    message: '两次输入密码不相同',
    path: ['pwd2'],
  })

export type RegisterValues = z.infer<typeof registerSchema>
